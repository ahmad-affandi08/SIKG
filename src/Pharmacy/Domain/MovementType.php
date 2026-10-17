<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

enum MovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stok masuk',
            self::Out => 'Stok keluar',
            self::Adjustment => 'Penyesuaian',
        };
    }
}
