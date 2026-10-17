<?php

declare(strict_types=1);

namespace Src\Patient\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Src\Patient\Application\Merge\MergePatients;
use Src\Patient\Application\ViewPatient;
use Src\Patient\Domain\PatientRecord;
use Src\Patient\Presentation\Http\Requests\MergePatientRequest;
use Src\Shared\Application\RequiredFields;

/**
 * PAS-07: penggabungan pasien ganda oleh Admin (izin patient.merge). Pasien utama ada di URL; pasien ganda dipilih lewat
 * pencarian dan dikirim sebagai id (bukan data pribadi) agar tidak ada PHI di URL (M11).
 */
final class PatientMergeController
{
    public function create(Request $request, string $patient, ViewPatient $view, MergePatients $merge): Response
    {
        $source = $request->query('source');
        $rules = (new MergePatientRequest)->rules();

        if (! is_string($source) || preg_match('/^[0-9A-Z]{26}$/', $source) !== 1) {
            $data = $view($patient, 'patients.merge');

            return Inertia::render('Patients/Merge', [
                'required' => RequiredFields::from($rules),
                'target' => $this->present($data['patient']),
                'source' => null,
                'counts' => [],
                'warnings' => [],
                'blocker' => null,
            ]);
        }

        $preview = $merge->preview($patient, $source);

        return Inertia::render('Patients/Merge', [
            'required' => RequiredFields::from($rules),
            'target' => $this->present($preview['target']),
            'source' => $this->present($preview['source']),
            'counts' => $preview['counts'],
            'warnings' => $preview['warnings'],
            'blocker' => $preview['blocker'],
        ]);
    }

    public function store(MergePatientRequest $request, string $patient, MergePatients $merge): RedirectResponse
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        $merge($id, $patient, $request->string('source_id')->toString(), $request->string('reason')->toString());

        return to_route('patients.show', ['patient' => $patient])->with('success', 'Data pasien ganda digabung. Riwayatnya kini ada pada pasien ini.');
    }

    /**
     * @return array<string, string|null>
     */
    private function present(PatientRecord $record): array
    {
        $p = $record->profile;

        return [
            'id' => $record->id,
            'medical_record_no' => $record->medicalRecordNo,
            'name' => $p->name,
            'nik' => $p->nik?->masked(),
            'birth_date' => $p->birthDate->format('Y-m-d'),
            'sex' => $p->sex->label(),
            'address' => $p->address,
            'phone' => $p->phone?->local(),
        ];
    }
}
