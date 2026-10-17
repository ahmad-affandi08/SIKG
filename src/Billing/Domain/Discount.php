<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use Src\Shared\Domain\Money;

/**
 * Diskon (KSR-02): persen (basis poin, 1% = 100) atau nominal rupiah, dengan alasan wajib. Hasilnya tidak pernah
 * melebihi dasar yang didiskon.
 */
final readonly class Discount
{
    public string $reason;

    public function __construct(public DiscountKind $kind, public int $value, string $reason)
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidBillingData('discount.reason_required', 'Alasan diskon wajib diisi.');
        }
        if (mb_strlen($reason) > 255) {
            throw new InvalidBillingData('discount.reason_too_long', 'Alasan diskon maksimal 255 karakter.');
        }
        if ($kind === DiscountKind::Percent && ($value < 1 || $value > 10000)) {
            throw new InvalidBillingData('discount.invalid_percent', 'Diskon persen harus antara 0,01% dan 100%.');
        }
        if ($kind === DiscountKind::Amount && $value < 1) {
            throw new InvalidBillingData('discount.invalid_amount', 'Diskon nominal harus lebih dari nol.');
        }
        $this->reason = $reason;
    }

    public function amountOn(Money $base): Money
    {
        $raw = $this->kind === DiscountKind::Percent ? $base->percent($this->value) : Money::rupiah($this->value);

        return $raw->greaterThan($base) ? $base : $raw;
    }

    /**
     * Persentase efektif dalam basis poin terhadap dasar, untuk pemeriksaan batas diskon per peran. Dibulatkan ke atas
     * agar batas tidak terlampaui oleh pembulatan.
     */
    public function basisPointsOn(Money $base): int
    {
        if ($base->isZero()) {
            return 0;
        }

        return intdiv($this->amountOn($base)->amount * 10000 + $base->amount - 1, $base->amount);
    }
}
