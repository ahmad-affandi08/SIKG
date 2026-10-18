<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

/**
 * Pengaturan poin loyalitas satu klinik. Poin didapat per kelipatan belanja (`rupiahPerPoint`) pada tagihan yang lunas dan
 * ditukar dengan nilai `pointValueAmount` rupiah per poin, minimal `minRedeemPoints` poin sekali tukar.
 */
final readonly class LoyaltySettings
{
    public function __construct(public bool $enabled, public int $rupiahPerPoint, public int $pointValueAmount, public int $minRedeemPoints) {}

    public static function disabled(): self
    {
        return new self(false, 10000, 1000, 10);
    }

    /** Poin dari total tagihan lunas, dibulatkan ke bawah. */
    public function pointsFor(int $paidTotal): int
    {
        return $this->enabled && $this->rupiahPerPoint > 0 ? intdiv(max(0, $paidTotal), $this->rupiahPerPoint) : 0;
    }

    /** Poin terbanyak yang masih bernilai tidak melebihi `subtotal`. */
    public function maxPointsFor(int $subtotal): int
    {
        return $this->pointValueAmount > 0 ? intdiv(max(0, $subtotal), $this->pointValueAmount) : 0;
    }
}
