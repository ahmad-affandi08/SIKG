<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Patient\Application\ArchivePatient;
use Src\Patient\Application\ManageMedicalHistory;
use Src\Patient\Application\RecordConsent;
use Src\Patient\Application\RegisterPatient;
use Src\Patient\Application\UpdatePatient;
use Src\Patient\Application\ViewPatient;
use Src\Patient\Domain\ConsentText;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Domain\PatientProfile;
use Src\Patient\Domain\PatientRecord;
use Src\Patient\Domain\Severity;
use Src\Patient\Domain\Sex;
use Src\Patient\Presentation\Http\Requests\MedicalHistoryRequest;
use Src\Patient\Presentation\Http\Requests\PatientRequest;
use Src\Shared\Application\Clock;
use Src\Shared\Application\RequiredFields;

/**
 * PAS-01, PAS-04, PAS-05, PAS-06. Izin dipasang per route (can:patient.*).
 */
final class PatientController
{
    public function __construct(private readonly Clock $clock) {}

    public function index(): Response
    {
        return Inertia::render('Patients/Index', ['sexes' => $this->sexOptions()]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Patients/Form', [
            // Persetujuan data wajib saat pendaftaran (PAS-05); ditegakkan di use case, bukan di aturan validasi.
            'required' => [...RequiredFields::from(PatientRequest::createFrom($request)->rules()), 'consent'],
            'patient' => null,
            'consent' => ['version' => ConsentText::VERSION, 'text' => ConsentText::BODY],
            ...$this->options(),
        ]);
    }

    public function store(PatientRequest $request, RegisterPatient $register): RedirectResponse
    {
        $id = $register(
            $this->actorId($request),
            $request->toDraft(),
            $request->boolean('consent'),
            $request->boolean('acknowledge_duplicates'),
        );

        return to_route('patients.show', ['patient' => $id])->with('success', 'Pasien didaftarkan.');
    }

    public function show(Request $request, string $patient, ViewPatient $view): Response
    {
        $data = $view($patient, 'patients.show');
        $canUpdate = (bool) $request->user()?->can('patient.update');

        return Inertia::render('Patients/Show', [
            'patient' => $this->present($data['patient'], mask: true),
            'histories' => array_map(fn ($h) => $h->toArray(), $data['histories']),
            'consents' => array_map(fn ($c) => [
                'version' => $c->version,
                'given_at' => $c->givenAt->format('c'),
                'recorded_by' => $c->recordedBy,
            ], $data['consents']),
            'currentConsentVersion' => ConsentText::VERSION,
            'can' => [
                'update' => $canUpdate,
                'archive' => (bool) $request->user()?->can('patient.delete'),
                'merge' => (bool) $request->user()?->can('patient.merge'),
                'history' => (bool) $request->user()?->can('patient.history.manage'),
                'timeline' => (bool) $request->user()?->can('encounter.view'),
            ],
            ...$this->historyOptions(),
        ]);
    }

    public function edit(Request $request, string $patient, ViewPatient $view): Response
    {
        $data = $view($patient, 'patients.edit');

        return Inertia::render('Patients/Form', [
            'required' => RequiredFields::from(PatientRequest::createFrom($request)->rules()),
            'patient' => $this->present($data['patient'], mask: false),
            'consent' => null,
            ...$this->options(),
        ]);
    }

    public function update(PatientRequest $request, string $patient, UpdatePatient $update): RedirectResponse
    {
        $update($this->actorId($request), $patient, $request->toDraft(), $request->boolean('acknowledge_duplicates'));

        return to_route('patients.show', ['patient' => $patient])->with('success', 'Data pasien disimpan.');
    }

    public function archive(Request $request, string $patient, ArchivePatient $archive): RedirectResponse
    {
        $archive($this->actorId($request), $patient);

        return to_route('patients.index')->with('success', 'Pasien diarsipkan. Riwayat tetap tersimpan.');
    }

    public function historyForm(Request $request, string $patient, ViewPatient $view): Response
    {
        $data = $view($patient, 'patients.history.create');

        return Inertia::render('Patients/HistoryForm', [
            'required' => RequiredFields::from(MedicalHistoryRequest::createFrom($request)->rules()),
            'patient' => [
                'id' => $patient,
                'name' => $data['patient']->profile->name,
                'medical_record_no' => $data['patient']->medicalRecordNo,
            ],
            ...$this->historyOptions(),
        ]);
    }

    public function addHistory(MedicalHistoryRequest $request, string $patient, ManageMedicalHistory $history): RedirectResponse
    {
        $history->add($this->actorId($request), $patient, $request->toEntry());

        return to_route('patients.show', ['patient' => $patient])->with('success', 'Riwayat medis ditambahkan.');
    }

    public function removeHistory(Request $request, string $patient, string $entry, ManageMedicalHistory $history): RedirectResponse
    {
        $history->remove($this->actorId($request), $patient, $entry);

        return back()->with('success', 'Riwayat medis dihapus.');
    }

    public function recordConsent(Request $request, string $patient, RecordConsent $consent): RedirectResponse
    {
        $consent($this->actorId($request), $patient);

        return back()->with('success', 'Persetujuan data pasien dicatat.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PatientRecord $record, bool $mask): array
    {
        $p = $record->profile;

        return [
            'id' => $record->id,
            'medical_record_no' => $record->medicalRecordNo,
            'name' => $p->name,
            'nik' => $p->nik === null ? null : ($mask ? $p->nik->masked() : $p->nik->value),
            'nik_missing_reason' => $p->nikMissingReason,
            'birth_date' => $p->birthDate->format('Y-m-d'),
            'sex' => $p->sex->value,
            'address' => $p->address,
            'phone' => $p->phone?->local(),
            'occupation' => $p->occupation,
            'emergency_name' => $p->emergencyContact?->name,
            'emergency_relation' => $p->emergencyContact?->relation,
            'emergency_phone' => $p->emergencyContact?->phone->local(),
            'guardian_name' => $p->guardian?->name,
            'guardian_relation' => $p->guardian?->relation,
            'guardian_phone' => $p->guardian?->phone?->local(),
            'is_minor' => $p->age($this->clock->now()) < PatientProfile::ADULT_AGE,
            'has_consent' => $record->consentAt !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return ['sexes' => $this->sexOptions()];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function sexOptions(): array
    {
        return array_map(fn (Sex $s) => ['value' => $s->value, 'label' => $s->label()], Sex::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private function historyOptions(): array
    {
        return [
            'historyTypes' => array_map(fn (MedicalHistoryType $t) => ['value' => $t->value, 'label' => $t->label()], MedicalHistoryType::cases()),
            'severities' => array_map(fn (Severity $s) => ['value' => $s->value, 'label' => $s->label()], Severity::cases()),
        ];
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
