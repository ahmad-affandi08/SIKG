<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ordered => 'Dipesan',
            self::Partial => 'Diterima sebagian',
            self::Received => 'Diterima lengkap',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
