<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Money;

/**
 * P2-05: pesanan pembelian ke pemasok. draft -> ordered -> partial -> received; draft atau ordered (belum ada penerimaan)
 * dapat dibatalkan. Status diturunkan dari jumlah diterima, tidak diisi manual (M7). Stok bertambah hanya lewat penerimaan.
 */
final class PurchaseOrder
{
    public const MAX_QUANTITY = 1_000_000;

    /**
     * @param  list<PurchaseOrderItem>  $items
     */
    private function __construct(
        public readonly string $id,
        public readonly string $poNo,
        public readonly string $supplierId,
        private PurchaseOrderStatus $status,
        public readonly ?string $expectedDate,
        public readonly ?string $note,
        private ?DateTimeImmutable $orderedAt,
        private readonly array $items,
        public readonly int $version,
    ) {}

    /**
     * @param  list<PurchaseOrderItem>  $items
     */
    public static function draft(string $id, string $poNo, string $supplierId, ?string $expectedDate, ?string $note, array $items): self
    {
        if ($items === []) {
            throw new InvalidPharmacyData('purchase.items_required', 'Pesanan pembelian minimal memuat satu obat.');
        }
        $seen = [];
        foreach ($items as $item) {
            if ($item->quantityOrdered < 1 || $item->quantityOrdered > self::MAX_QUANTITY) {
                throw new InvalidPharmacyData('purchase.invalid_quantity', 'Jumlah pesanan harus bilangan bulat positif.');
            }
            if ($item->unitCostAmount < 0) {
                throw new InvalidPharmacyData('purchase.invalid_cost', 'Harga beli tidak boleh negatif.');
            }
            if (isset($seen[$item->medicineId])) {
                throw new InvalidPharmacyData('purchase.duplicate_medicine', 'Satu obat hanya boleh muncul sekali dalam satu pesanan.');
            }
            $seen[$item->medicineId] = true;
        }

        return new self($id, $poNo, $supplierId, PurchaseOrderStatus::Draft, $expectedDate, $note, null, $items, 1);
    }

    /**
     * @param  list<PurchaseOrderItem>  $items
     */
    public static function restore(string $id, string $poNo, string $supplierId, PurchaseOrderStatus $status, ?string $expectedDate, ?string $note, ?DateTimeImmutable $orderedAt, array $items, int $version): self
    {
        return new self($id, $poNo, $supplierId, $status, $expectedDate, $note, $orderedAt, $items, $version);
    }

    public function status(): PurchaseOrderStatus
    {
        return $this->status;
    }

    public function orderedAt(): ?DateTimeImmutable
    {
        return $this->orderedAt;
    }

    /**
     * @return list<PurchaseOrderItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): Money
    {
        $total = Money::zero();
        foreach ($this->items as $item) {
            $total = $total->add($item->lineTotal());
        }

        return $total;
    }

    public function markOrdered(DateTimeImmutable $at): void
    {
        if ($this->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidPharmacyData('purchase.not_draft', 'Hanya pesanan draft yang dapat dipesan.');
        }
        $this->status = PurchaseOrderStatus::Ordered;
        $this->orderedAt = $at;
    }

    public function cancel(): void
    {
        if ($this->status !== PurchaseOrderStatus::Draft && $this->status !== PurchaseOrderStatus::Ordered) {
            throw new InvalidPharmacyData('purchase.cannot_cancel', 'Pesanan yang sudah diterima sebagian atau lengkap tidak dapat dibatalkan.');
        }
        $this->status = PurchaseOrderStatus::Cancelled;
    }

    /**
     * Mencatat penerimaan barang pada satu baris; jumlah tidak boleh melebihi sisa pesanan.
     */
    public function receive(string $itemId, int $quantity): PurchaseOrderItem
    {
        if ($this->status !== PurchaseOrderStatus::Ordered && $this->status !== PurchaseOrderStatus::Partial) {
            throw new InvalidPharmacyData('purchase.not_receivable', 'Pesanan harus berstatus dipesan atau diterima sebagian untuk menerima barang.');
        }
        $item = $this->item($itemId);
        if ($quantity < 1 || $quantity > $item->outstanding()) {
            throw new InvalidPharmacyData('purchase.over_receipt', 'Jumlah diterima harus antara 1 dan sisa pesanan ('.$item->outstanding().').');
        }
        $item->accept($quantity);
        $this->status = $this->allReceived() ? PurchaseOrderStatus::Received : PurchaseOrderStatus::Partial;

        return $item;
    }

    private function item(string $itemId): PurchaseOrderItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        throw new InvalidPharmacyData('purchase.item_not_found', 'Baris pesanan tidak ditemukan.');
    }

    private function allReceived(): bool
    {
        foreach ($this->items as $item) {
            if ($item->outstanding() > 0) {
                return false;
            }
        }

        return true;
    }
}
