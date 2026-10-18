<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Loyalty;

use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PromotionRepository;
use Src\Billing\Domain\PromotionGuard;

final readonly class EloquentPromotionGuard implements PromotionGuard
{
    public function __construct(private PromotionRepository $promotions, private PackageRepository $packages) {}

    public function invoiceHasPromotion(string $invoiceId): bool
    {
        return $this->promotions->activeForInvoice($invoiceId) !== null;
    }

    public function itemHasPackageUsage(string $itemId): bool
    {
        return $this->packages->itemHasUsage($itemId);
    }
}
