<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Clinical\Application\EncounterEntries;
use Src\Clinical\Application\EncounterLifecycle;
use Src\Clinical\Application\EncounterNotes;
use Src\Clinical\Application\EncounterWorkspace;
use Src\Clinical\Application\OpenEncounter;
use Src\Clinical\Domain\Jaw;
use Src\Clinical\Domain\Surface;
use Src\Clinical\Domain\Vitals;

/**
 * RM-01 sampai RM-07. Izin dipasang per route (can:encounter.*); aturan kunci catatan dijaga di Application.
 */
final class EncounterController
{
    public function show(Request $request, string $encounter, EncounterWorkspace $workspace): Response
    {
        return Inertia::render('Encounters/Show', [
            ...$workspace($encounter, 'encounters.show'),
            'can' => [
                'write' => (bool) $request->user()?->can('encounter.write'),
                'start' => (bool) $request->user()?->can('encounter.create'),
                'close' => (bool) $request->user()?->can('encounter.close'),
                'addendum' => (bool) $request->user()?->can('encounter.addendum'),
                'prescribe' => (bool) $request->user()?->can('prescription.create'),
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
        $data = $request->validate([
            'diagnosis_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['diagnosis_id.required' => 'Diagnosa wajib dipilih.']);

        $entries->addDiagnosis($this->actorId($request), $encounter, $data['diagnosis_id'], $data['tooth_fdi'] ?? null, $data['notes'] ?? null);

        return back()->with('success', 'Diagnosa ditambahkan.');
    }

    public function removeDiagnosis(Request $request, string $encounter, string $entry, EncounterEntries $entries): RedirectResponse
    {
        $entries->removeDiagnosis($this->actorId($request), $encounter, $entry);

        return back()->with('success', 'Diagnosa dihapus.');
    }

    public function addProcedure(Request $request, string $encounter, EncounterEntries $entries): RedirectResponse
    {
        $data = $request->validate([
            'procedure_id' => ['required', 'string', 'size:26'],
            'tooth_fdi' => ['nullable', 'integer'],
            'surfaces' => ['nullable', 'array', 'max:5'],
            'surfaces.*' => ['string', 'size:1'],
            'jaw' => ['nullable', 'string', 'max:10'],
            'quantity' => ['required', 'integer', 'between:1,'.EncounterEntries::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['procedure_id.required' => 'Tindakan wajib dipilih.', 'quantity.required' => 'Jumlah wajib diisi.']);

        $entries->addProcedure(
            $this->actorId($request),
            $encounter,
            $data['procedure_id'],
            $data['tooth_fdi'] ?? null,
            array_values($data['surfaces'] ?? []),
            $data['jaw'] ?? null,
            (int) $data['quantity'],
            $data['notes'] ?? null,
        );

        return back()->with('success', 'Tindakan ditambahkan.');
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
        $data = $request->validate([
            'body' => ['required', 'string', 'max:8000'],
            'reason' => ['required', 'string', 'max:255'],
        ], ['body.required' => 'Isi addendum wajib diisi.', 'reason.required' => 'Alasan addendum wajib diisi.']);

        $notes->addAddendum($this->actorId($request), $encounter, $data['body'], $data['reason']);

        return back()->with('success', 'Addendum ditambahkan.');
    }

    private function actorId(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }
}
