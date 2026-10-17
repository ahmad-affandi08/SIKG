<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

enum PaymentType: string
{
    case Pay = 'pay';
    case Dp = 'dp';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Pay => 'Pembayaran',
            self::Dp => 'Uang muka',
            self::Refund => 'Pengembalian',
        };
    }
}
