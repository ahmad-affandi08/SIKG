<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

enum Dentition: string
{
    case Permanent = 'permanent';
    case Primary = 'primary';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Gigi dewasa',
            self::Primary => 'Gigi sulung',
            self::Mixed => 'Gigi campuran',
        };
    }
}
