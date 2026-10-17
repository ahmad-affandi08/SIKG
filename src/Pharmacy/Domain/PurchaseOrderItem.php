<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use Src\Shared\Domain\Money;

/**
 * Baris pesanan pembelian. Harga beli disalin saat dipesan (M7, MST-08) sehingga perubahan harga kemudian tidak mengubah pesanan.
 */
final class PurchaseOrderItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $medicineId,
        public readonly int $quantityOrdered,
        private int $quantityReceived,
        public readonly int $unitCostAmount,
    ) {}

    public function received(): int
    {
        return $this->quantityReceived;
    }

    public function outstanding(): int
    {
        return $this->quantityOrdered - $this->quantityReceived;
    }

    public function lineTotal(): Money
    {
        return Money::rupiah($this->unitCostAmount)->multiply($this->quantityOrdered);
    }

    /**
     * @internal dipanggil oleh PurchaseOrder::receive
     */
    public function accept(int $quantity): void
    {
        $this->quantityReceived += $quantity;
    }
}
