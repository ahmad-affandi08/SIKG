<?php

declare(strict_types=1);

namespace Src\Billing\Contracts;

/**
 * Ringkasan tagihan untuk penagihan ke penjamin (P2-02): nomor, pasien, status, total, dan yang sudah dibayar. Tanpa rincian item.
 */
final readonly class InvoiceClaimInfo
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $patientId,
        public string $status,
        public string $statusLabel,
        public int $totalAmount,
        public int $paidAmount,
    ) {}

    /** Tagihan yang sudah terbit dan belum dibatalkan dapat ditagihkan ke penjamin. */
    public function claimable(): bool
    {
        return in_array($this->status, ['unpaid', 'partial', 'paid'], true);
    }
}
