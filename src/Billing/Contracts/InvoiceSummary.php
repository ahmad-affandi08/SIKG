<?php

declare(strict_types=1);

namespace Src\Billing\Contracts;

/**
 * Ringkasan tagihan untuk konteks lain (layar pemeriksaan): tanpa PHI, hanya nomor, status, dan jumlah.
 */
final readonly class InvoiceSummary
{
    public function __construct(
        public string $id,
        public ?string $number,
        public string $status,
        public string $statusLabel,
        public int $totalAmount,
        public int $paidAmount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'total_amount' => $this->totalAmount,
            'paid_amount' => $this->paidAmount,
        ];
    }
}
