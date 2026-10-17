<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Contracts\PatientIdentity;
use Src\Patient\Contracts\PatientIdentityLookup;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Shared\Application\PiiProtector;

final readonly class EloquentPatientIdentityLookup implements PatientIdentityLookup
{
    public function __construct(private PiiProtector $pii) {}

    public function identity(string $patientId): ?PatientIdentity
    {
        $patient = PatientModel::withTrashed()->find($patientId);
        if ($patient === null) {
            return null;
        }

        return new PatientIdentity(
            $patient->id,
            $patient->name,
            $patient->nik_encrypted === null ? null : $this->pii->decrypt($patient->nik_encrypted),
            $patient->birth_date->toDateString(),
            $patient->sex,
        );
    }
}
