<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

interface PromotionRepository
{
    public function activeForInvoice(string $invoiceId): ?PromotionRecord;

    public function add(PromotionRecord $promotion, string $actorId): void;

    /**
     * Melepas promosi aktif (status released); mengembalikan catatannya atau null bila tidak ada.
     */
    public function release(string $invoiceId, string $reason): ?PromotionRecord;
}
