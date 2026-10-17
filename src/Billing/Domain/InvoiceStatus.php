<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

/**
 * Status tagihan diturunkan dari daur hidup, total, dan jumlah dibayar; tidak pernah diisi manual (M7).
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Unpaid => 'Belum dibayar',
            self::Partial => 'Dibayar sebagian',
            self::Paid => 'Lunas',
            self::Void => 'Dibatalkan',
        };
    }
}
