<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

final readonly class PatientSummary
{
    public function __construct(public string $id, public string $medicalRecordNo, public string $name) {}
}
