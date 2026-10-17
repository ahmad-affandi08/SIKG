<?php

declare(strict_types=1);

namespace Src\Scheduling\Contracts;

use DateTimeImmutable;

final readonly class AppointmentInfo
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $doctorId,
        public ?string $chairId,
        public string $status,
        public DateTimeImmutable $startsAt,
    ) {}
}
