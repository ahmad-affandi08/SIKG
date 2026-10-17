<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use Src\Shared\Domain\Money;

/**
 * Satu baris tagihan. Harga satuan adalah SALINAN tarif saat baris dibuat (MST-08); perubahan tarif di katalog tidak
 * menyentuh baris yang sudah ada. Total diturunkan: jumlah x harga dikurangi diskon.
 */
final class InvoiceItem
{
    public const SOURCE_PROCEDURE = 'procedure';

    public const SOURCE_MEDICINE = 'medicine';

    public const SOURCE_MANUAL = 'manual';

    private function __construct(
        public readonly string $id,
        public readonly string $sourceType,
        public readonly ?string $sourceId,
        public readonly string $description,
        private int $quantity,
        public readonly Money $unitPrice,
        private ?Discount $discount,
    ) {}

    public static function create(string $id, string $sourceType, ?string $sourceId, string $description, int $quantity, Money $unitPrice): self
    {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidBillingData('invoice.item_description_required', 'Uraian item wajib diisi.');
        }
        if (mb_strlen($description) > 255) {
            throw new InvalidBillingData('invoice.item_description_too_long', 'Uraian item maksimal 255 karakter.');
        }
        self::guardQuantity($quantity);

        return new self($id, $sourceType, $sourceId, $description, $quantity, $unitPrice, null);
    }

    public static function reconstitute(string $id, string $sourceType, ?string $sourceId, string $description, int $quantity, Money $unitPrice, ?Discount $discount): self
    {
        return new self($id, $sourceType, $sourceId, $description, $quantity, $unitPrice, $discount);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function discount(): ?Discount
    {
        return $this->discount;
    }

    public function isManual(): bool
    {
        return $this->sourceType === self::SOURCE_MANUAL;
    }

    public function gross(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }

    public function discountAmount(): Money
    {
        return $this->discount?->amountOn($this->gross()) ?? Money::zero();
    }

    public function total(): Money
    {
        return $this->gross()->subtract($this->discountAmount());
    }

    public function changeQuantity(int $quantity): void
    {
        self::guardQuantity($quantity);
        $this->quantity = $quantity;
    }

    public function applyDiscount(?Discount $discount): void
    {
        $this->discount = $discount;
    }

    private static function guardQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 9999) {
            throw new InvalidBillingData('invoice.invalid_quantity', 'Jumlah harus antara 1 dan 9999.');
        }
    }
}
