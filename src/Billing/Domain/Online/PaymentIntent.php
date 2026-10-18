<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Online;

use DateTimeImmutable;

final readonly class PaymentIntent
{
    public function __construct(
        public string $id,
        public string $invoiceId,
        public string $orderId,
        public int $amount,
        public IntentStatus $status,
        public ?string $paymentUrl,
        public ?string $gatewayRef,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $paidAt,
        public ?string $paymentId,
        public ?string $note,
        public ?string $createdBy,
    ) {}
}
