<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Billing\Contracts\InvoiceLookup;
use Src\Clinic\Contracts\ServiceProfileLookup;
use Src\Clinical\Application\EncounterEntries;
use Src\Clinical\Application\EncounterLifecycle;
use Src\Clinical\Application\EncounterNotes;
use Src\Clinical\Application\EncounterWorkspace;
use Src\Clinical\Application\OdontogramService;
use Src\Clinical\Application\OpenEncounter;
use Src\Clinical\Application\TreatmentPlans;
use Src\Clinical\Domain\Jaw;
use Src\Clinical\Domain\Surface;
use Src\Clinical\Domain\Vitals;
use Src\Shared\Application\RequiredFields;

/**
 * RM-01 sampai RM-07. Izin dipasang per route (can:encounter.*); aturan kunci catatan dijaga di Application.
 */
final class EncounterController
{
    private const FORMS = [
        'diagnosis' => ['page' => 'Encounters/DiagnosisForm', 'tab' => 'diagnosis'],
        'procedure' => ['page' => 'Encounters/ProcedureForm', 'tab' => 'procedures'],
        'file' => ['page' => 'Encounters/FileForm', 'tab' => 'files'],
        'addendum' => ['page' => 'Encounters/AddendumForm', 'tab' => 'addenda'],
    ];

    private const TABS = ['anamnesis', 'soap', 'odontogram', 'diagnosis', 'procedures', 'prescriptions', 'files', 'addenda'];

    /**
     * Halaman formulir tambah diagnosa, tindakan, berkas, atau addendum. Diagnosa, tindakan, dan berkas hanya selama
     * kunjungan berjalan; addendum hanya setelah kunjungan ditutup (RM-07).
     */
    public function entryForm(Request $request, string $encounter, EncounterWorkspace $workspace, TreatmentPlans $plans): Response|RedirectResponse
    {
        $form = (string) $request->route('form');
        abort_unless(isset(self::FORMS[$form]), 404);
        $config = self::FORMS[$form];

        $e = $workspace($encounter, 'encounters.'.$form.'.create')['encounter'];
        $closed = (bool) $e['is_closed'];
        $back = to_route('encounters.show', ['encounter' => $encounter, 'tab' => $config['tab']]);
        if ($form === 'addendum' && ! $closed) {
            return $back->with('error', 'Addendum hanya untuk kunjungan yang sudah ditutup.');
        }
        if ($form !== 'addendum' && $closed) {
            return $back->with('error', 'Kunjungan sudah ditutup. Perubahan hanya lewat addendum.');
        }

        $rules = match ($form) {
            'diagnosis' => self::diagnosisRules(),
            'procedure' => self::procedureRules(),
            'file' => ClinicalFileController::uploadRules(),
            default => self::addendumRules(),
        };

        return Inertia::render($config['page'], [
            'required' => RequiredFields::from($rules),
            'encounter' => [
                'id' => $e['id'],
                'patient_name' => $e['patient_name'],
                'medical_record_no' => $e['medical_record_no'],
            ],
            'jaws' => array_map(fn (Jaw $j) => ['value' => $j->value, 'label' => $j->label()], Jaw::cases()),
            'prefill' => $form === 'procedure' ? $this->planPrefill($request, (string) $e['patient_id'], $plans) : null,
        ]);
    }

    /**
     * RM-06: formulir tindakan terisi dari langkah rencana perawatan (`?plan_item=`), bila langkah itu milik pasien kunjungan dan masih terbuka.
     *
     * @return array<string, mixed>|null
     */
    private function planPrefill(Request $request, string $patientId, TreatmentPlans $plans): ?array
    {
        $itemId = $request->query('plan_item');
        if (! is_string($itemId) || preg_match('/^[0-9A-Z]{26}$/', $itemId) !== 1) {
            return null;
        }
        $found = $plans->itemFor($patientId, $itemId);
        $item = $found['item'];

        return [
            'plan_item_id' => $item->id,
            'plan_title' => $found['plan_title'],
            'procedure_id' => $item->procedureId,
            'code' => $found['procedure']->code,
            'name' => $found['procedure']->name,
            'scope' => $found['procedure']->scope,
            'price_amount' => $found['procedure']->priceAmount,
            'tooth_fdi' => $item->toothFdi,
            'quantity' => $item->quantity,
            'notes' => $item->notes,
        ];
    }

    public function show(Request $request, string $encounter, EncounterWorkspace $workspace, InvoiceLookup $invoices, ServiceProfileLookup $profile): Response
    {
        $tab = $request->query('tab');

        return Inertia::render('Encounters/Show', [
            ...$workspace($encounter, 'encounters.show'),
            'initialTab' => is_string($tab) && in_array($tab, self::TABS, true) ? $tab : 'anamnesis',
            'invoice' => $request->user()?->can('invoice.view') === true ? $invoices->forEncounter($encounter)?->toArray() : null,
            'can' => [
                'write' => (bool) $request->user()?->can('encounter.write'),
                'start' => (bool) $request->user()?->can('encounter.create'),
                'close' => (bool) $request->user()?->can('encounter.close'),
                'addendum' => (bool) $request->user()?->can('encounter.addendum'),
                'prescribe' => (bool) $request->user()?->can('prescription.create'),
                'odontogram' => $profile->current()->dental() && (bool) $request->user()?->can('odontogram.write'),
                'printPrescription' => (bool) $request->user()?->can('prescription.view'),
                'overrideStock' => (bool) $request->user()?->can('stock.override'),
            ],
            'surfaces' => array_map(fn (Surface $s) => ['value' => $s->value, 'label' => $s->label()], Surface::faces()),
            'jaws' => array_map(fn (Jaw $j) => ['value' => $j->value, 'label' => $j->label()], Jaw::cases()),
        ]);
    }

    public function store(Request $request, OpenEncounter $open): RedirectResponse
    {
        $data = $request->validate(['appointment_id' => ['required', 'string', 'size:26']], ['appointment_id.required' => 'Janji wajib dipilih.']);
        $id = $open->forAppointment($this->actorId($request), $data['appointment_id']);

        return to_route('encounters.show', ['encounter' => $id]);
    }

    public function start(Request $request, string $encounter, EncounterLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['chair_id' => ['nullable', 'string', 'size:26']]);
        $chair = $data['chair_id'] ?? null;
        $lifecycle->startExamination($this->actorId($request), $encounter, $chair !== null && $chair !== '' ? $chair : null);

        return back()->with('success', 'Pemeriksaan dimulai.');
    }

    public function anamnesis(Request $request, string $encounter, EncounterNotes $notes): RedirectResponse
    {
        $data = $request->validate([
            'chief_complaint' => ['nullable', 'string', 'max:2000'],
            'complaint_history' => ['nullable', 'string', 'max:4000'],
            'systolic' => ['nullable', 'integer'],
            'diastolic' => ['nullable', 'integer'],
            'pulse' => ['nullable', 'integer'],
            'temperature_tenths' => ['nullable', 'integer'],
            'respiratory_rate' => ['nullable', 'integer'],
            'oxygen_saturation' => ['nullable', 'integer'],
            'weight_tenths_kg' => ['nullable', 'integer'],
            'height_cm' => ['nullable', 'integer'],
        ]);

        $notes->recordAnamnesis(
            $this->actorId($request),
            $encounter,
            $data['chief_complaint'] ?? null,
            $data['complaint_history'] ?? null,
            Vitals::fromArray($data),
            (bool) $request->user()?->can('encounter.close'),
        );

        return back()->with('success', 'Anamnesis disimpan.');
    }

    public function notes(Request $request, string $encounter, EncounterNotes $notes): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(EncounterNotes::FIELDS) as $key) {
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:8000'];
        }
        $data = $request->validate($rules);

        $notes->writeNotes($this->actorId($request), $encounter, $data, (bool) $request->user()?->can('encounter.close'));

        return back()->with('success', 'Catatan disimpan.');
    }

    public function addDiagnosis(Request $request, string $encounter, EncounterEntries $entries): RedirectResponse
    {
        $data = $request->validate(self::diagnosisRules(), ['diagnosis_id.required' => 'Diagnosa wajib dipilih.']);

        $entries->addDiagnosis($this->actorId($request), $encounter, $data['diagnosis_id'], $data['tooth_fdi'] ?? null, $data['notes'] ?? null);

        return to_route('encounters.show', ['encounter' => $encounter, 'tab' => 'diagnosis'])->with('success', 'Diagnosa ditambahkan.');
    }

    public function removeDiagnosis(Request $request, string $encounter, string $entry, EncounterEntries $entries): RedirectResponse
    {
        $entries->removeDiagnosis($this->actorId($request), $encounter, $entry);

        return back()->with('success', 'Diagnosa dihapus.');
    }

    public function addProcedure(Request $request, string $encounter, EncounterEntries $entries): RedirectResponse
    {
        $data = $request->validate(self::procedureRules(), ['procedure_id.required' => 'Tindakan wajib dipilih.', 'quantity.required' => 'Jumlah wajib diisi.']);

        $entries->addProcedure(
            $this->actorId($request),
            $encounter,
            $data['procedure_id'],
            $data['tooth_fdi'] ?? null,
            array_values($data['surfaces'] ?? []),
            $data['jaw'] ?? null,
            (int) $data['quantity'],
            $data['notes'] ?? null,
            $data['plan_item_id'] ?? null,
        );

        return to_route('encounters.show', ['encounter' => $encounter, 'tab' => 'procedures'])->with('success', 'Tindakan ditambahkan.');
    }

    /** ODO-05: dokter menerapkan atau mengabaikan usulan pembaruan odontogram dari sebuah tindakan. */
    public function resolveOdontogram(Request $request, string $encounter, string $entry, OdontogramService $odontogram): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:apply,dismiss']], ['action.required' => 'Pilih terapkan atau abaikan.']);
        $apply = $data['action'] === 'apply';
        $odontogram->resolveSuggestion($this->actorId($request), $encounter, $entry, $apply);

        return back()->with('success', $apply ? 'Odontogram diperbarui.' : 'Usulan pembaruan odontogram diabaikan.');
    }

    public function removeProcedure(Request $request, string $encounter, string $entry, EncounterEntries $entries): RedirectResponse
    {
        $entries->removeProcedure($this->actorId($request), $encounter, $entry);

        return back()->with('success', 'Tindakan dihapus.');
    }

    public function close(Request $request, string $encounter, EncounterLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->close($this->actorId($request), $encounter);

        return back()->with('success', 'Kunjungan ditutup. Catatan terkunci.');
    }

    public function addendum(Request $request, string $encounter, EncounterNotes $notes): RedirectResponse
    {
        $data = $request->validate(self::addendumRules(), ['body.required' => 'Isi addendum wajib diisi.', 'reason.required' => 'Alasan addendum wajib diisi.']);

        $notes->addAddendum($this->actorId($request), $encounter, $data['body'], $data['reason']);

        return to_route('encounters.show', ['encounter' => $encounter, 'tab' => 'addenda'])->with('success', 'Addendum ditambahkan.');
    }

    /**
     * @return array<string, list<string>>
     */
    private static function diagnosisRules(): array
    {
        return [
            'diagnosis_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function procedureRules(): array
    {
        return [
            'procedure_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'surfaces' => ['nullable', 'array', 'max:5'],
            'surfaces.*' => ['string', 'size:1'],
            'jaw' => ['nullable', 'string', 'max:10'],
            'quantity' => ['required', 'integer', 'between:1,'.EncounterEntries::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:500'],
            'plan_item_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function addendumRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:8000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
