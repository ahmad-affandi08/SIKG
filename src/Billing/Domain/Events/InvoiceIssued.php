<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class InvoiceIssued implements DomainEvent
{
    public function __construct(public string $invoiceId, public string $encounterId, public int $totalAmount, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'billing.invoice_issued';
    }
}
