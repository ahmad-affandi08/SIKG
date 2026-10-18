<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

/**
 * Pemeriksaan promosi aktif agar diskon manual tidak menimpa voucher, penukaran poin, atau pemakaian paket (P3-06).
 */
interface PromotionGuard
{
    public function invoiceHasPromotion(string $invoiceId): bool;

    public function itemHasPackageUsage(string $itemId): bool;
}
