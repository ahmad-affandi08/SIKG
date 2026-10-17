<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

final readonly class EncounterRecord
{
    public function __construct(
        public string $id,
        public string $patientId,
        public ?string $appointmentId,
        public string $doctorId,
        public ?string $chairId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $closedAt,
        public EncounterStatus $status,
        public ?string $chiefComplaint,
        public ?string $complaintHistory,
        public Vitals $vitals,
        public ?DateTimeImmutable $anamnesisAt,
        public int $version,
    ) {}

    public function isClosed(): bool
    {
        return $this->status === EncounterStatus::Closed;
    }
}
