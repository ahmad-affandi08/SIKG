<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

final readonly class PaymentReceived implements DomainEvent
{
    public function __construct(public string $invoiceId, public string $paymentId, public int $amount, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'billing.payment_received';
    }
}
