<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

/**
 * Promosi yang menempel pada satu tagihan: voucher atau penukaran poin. Hanya satu yang aktif per tagihan.
 */
final readonly class PromotionRecord
{
    public const VOUCHER = 'voucher';

    public const POINTS = 'points';

    public function __construct(
        public string $id,
        public string $invoiceId,
        public string $patientId,
        public string $type,
        public ?string $voucherId,
        public int $pointsUsed,
        public int $discountAmount,
    ) {}
}
