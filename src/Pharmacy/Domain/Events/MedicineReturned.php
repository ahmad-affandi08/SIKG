<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class MedicineReturned implements DomainEvent
{
    public function __construct(public string $encounterId, public string $prescriptionItemId, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'pharmacy.medicine_returned';
    }
}
