<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Money;

/**
 * Catatan pembayaran. Hanya bertambah: tidak pernah diubah atau dihapus; koreksi berupa transaksi pembalik (refund, KSR-07).
 */
final readonly class Payment
{
    public function __construct(
        public string $id,
        public string $methodId,
        public PaymentType $type,
        public Money $amount,
        public DateTimeImmutable $paidAt,
        public string $receivedBy,
        public ?string $referenceNo,
        public string $idempotencyKey,
        public ?string $receiptNo,
        public ?string $reason,
    ) {}

    public function isRefund(): bool
    {
        return $this->type === PaymentType::Refund;
    }
}
