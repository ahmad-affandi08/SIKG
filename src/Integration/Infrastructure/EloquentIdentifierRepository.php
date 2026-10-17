<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Src\Integration\Domain\IdentifierRepository;
use Src\Integration\Infrastructure\Eloquent\SatuSehatPatientModel;
use Src\Integration\Infrastructure\Eloquent\SatuSehatPractitionerModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentIdentifierRepository implements IdentifierRepository
{
    public function __construct(private PiiProtector $pii) {}

    public function patientIhs(string $patientId): ?string
    {
        return SatuSehatPatientModel::query()->where('patient_id', $patientId)->value('ihs_id');
    }

    public function rememberPatient(string $patientId, string $ihsId): void
    {
        $row = SatuSehatPatientModel::query()->where('patient_id', $patientId)->first() ?? (new SatuSehatPatientModel)->forceFill(['patient_id' => $patientId]);
        $row->forceFill(['ihs_id' => $ihsId])->save();
    }

    public function practitionerNik(string $doctorId): ?string
    {
        $cipher = SatuSehatPractitionerModel::query()->where('doctor_id', $doctorId)->value('nik_encrypted');

        return $cipher === null ? null : $this->pii->decrypt($cipher);
    }

    public function practitionerIhs(string $doctorId): ?string
    {
        return SatuSehatPractitionerModel::query()->where('doctor_id', $doctorId)->value('ihs_id');
    }

    public function savePractitionerNik(string $actorId, string $doctorId, ?string $nik): void
    {
        $row = SatuSehatPractitionerModel::query()->where('doctor_id', $doctorId)->first() ?? (new SatuSehatPractitionerModel)->forceFill(['doctor_id' => $doctorId, 'created_by' => $actorId]);
        // NIK baru membuat ID lama tidak berlaku; dicari ulang saat kirim berikutnya.
        $row->forceFill(['nik_encrypted' => $nik === null ? null : $this->pii->encrypt($nik), 'ihs_id' => null, 'updated_by' => $actorId])->save();
    }

    public function rememberPractitioner(string $doctorId, string $ihsId): void
    {
        SatuSehatPractitionerModel::query()->where('doctor_id', $doctorId)->update(['ihs_id' => $ihsId]);
    }

    public function practitionerStatuses(): array
    {
        $out = [];
        foreach (SatuSehatPractitionerModel::query()->get() as $row) {
            $out[$row->doctor_id] = ['has_nik' => $row->nik_encrypted !== null, 'ihs_id' => $row->ihs_id];
        }

        return $out;
    }
}
