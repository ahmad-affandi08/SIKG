<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class AppointmentMissed implements DomainEvent
{
    public function __construct(public string $appointmentId, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'scheduling.appointment_missed';
    }
}
