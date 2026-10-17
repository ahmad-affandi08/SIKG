<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * Pasien check-in. Clinical mendengarkan event ini untuk membuat kunjungan (RM-01). Hanya ID, tanpa PHI.
 */
final readonly class PatientCheckedIn implements DomainEvent
{
    public function __construct(
        public string $appointmentId,
        public string $patientId,
        public string $doctorId,
        public ?string $chairId,
        private DateTimeImmutable $at,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'scheduling.patient_checked_in';
    }
}
