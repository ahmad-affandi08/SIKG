<?php

declare(strict_types=1);

namespace Src\Clinical\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class EncounterClosed implements DomainEvent
{
    public function __construct(public string $encounterId, public string $patientId, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'clinical.encounter_closed';
    }
}
