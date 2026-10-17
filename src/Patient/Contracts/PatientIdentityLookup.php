<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

interface PatientIdentityLookup
{
    public function identity(string $patientId): ?PatientIdentity;
}
