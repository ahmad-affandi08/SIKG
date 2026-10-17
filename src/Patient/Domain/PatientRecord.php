<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use DateTimeImmutable;

final readonly class PatientRecord
{
    public function __construct(
        public string $id,
        public string $medicalRecordNo,
        public PatientProfile $profile,
        public ?DateTimeImmutable $consentAt,
    ) {}
}
