<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

use Src\Billing\Domain\Discount;
use Src\Billing\Domain\DiscountKind;
use Src\Billing\Domain\InvalidBillingData;
use Src\Shared\Domain\Money;

/**
 * P3-06: kode promo. Diskon persen (basis poin) atau nominal atas subtotal tagihan, dengan masa berlaku, batas pemakaian total
 * dan per pasien, serta subtotal minimum. Kode disimpan huruf besar tanpa spasi.
 */
final readonly class Voucher
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public DiscountKind $kind,
        public int $value,
        public int $minSubtotalAmount,
        public ?int $maxRedemptions,
        public int $maxPerPatient,
        public ?string $validFrom,
        public ?string $validTo,
        public bool $active,
    ) {}

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', $code) ?? '');
    }

    /**
     * @param  int  $appliedTotal  pemakaian aktif voucher ini
     * @param  int  $appliedByPatient  pemakaian aktif oleh pasien tagihan ini
     * @param  string  $today  YYYY-MM-DD menurut kalender klinik
     *
     * @throws InvalidBillingData
     */
    public function assertUsable(Money $subtotal, int $appliedTotal, int $appliedByPatient, string $today): void
    {
        if (! $this->active) {
            throw new InvalidBillingData('voucher.inactive', 'Kode promo tidak aktif.');
        }
        if (($this->validFrom !== null && $today < $this->validFrom) || ($this->validTo !== null && $today > $this->validTo)) {
            throw new InvalidBillingData('voucher.expired', 'Kode promo di luar masa berlaku.');
        }
        if ($subtotal->amount < $this->minSubtotalAmount) {
            throw new InvalidBillingData('voucher.min_subtotal', 'Subtotal tagihan belum mencapai minimum kode promo.');
        }
        if ($this->maxRedemptions !== null && $appliedTotal >= $this->maxRedemptions) {
            throw new InvalidBillingData('voucher.exhausted', 'Kode promo sudah mencapai batas pemakaian.');
        }
        if ($appliedByPatient >= $this->maxPerPatient) {
            throw new InvalidBillingData('voucher.patient_limit', 'Pasien ini sudah memakai kode promo sesuai batasnya.');
        }
    }

    public function discount(): Discount
    {
        return new Discount($this->kind, $this->value, 'Voucher '.$this->code);
    }
}
