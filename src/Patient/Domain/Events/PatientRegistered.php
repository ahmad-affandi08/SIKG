<?php

declare(strict_types=1);

namespace Src\Patient\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class PatientRegistered implements DomainEvent
{
    public function __construct(public string $patientId, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'patient.registered';
    }
}
