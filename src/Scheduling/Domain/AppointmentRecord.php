<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Janji temu. Waktu dalam UTC; tanggal antrean adalah tanggal lokal klinik (YYYY-MM-DD).
 */
final readonly class AppointmentRecord
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $doctorId,
        public ?string $chairId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public AppointmentStatus $status,
        public string $source,
        public ?int $queueNo,
        public ?string $queueDate,
        public ?string $complaint,
        public ?string $cancelReason,
        public int $version,
    ) {}

    public function slot(): TimeSlot
    {
        return new TimeSlot($this->startsAt, $this->endsAt);
    }
}
