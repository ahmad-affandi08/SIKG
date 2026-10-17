<?php

declare(strict_types=1);

namespace Src\Lab\Domain;

enum WorkType: string
{
    case Crown = 'crown';
    case Bridge = 'bridge';
    case Denture = 'denture';
    case Aligner = 'aligner';
    case Veneer = 'veneer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Crown => 'Mahkota',
            self::Bridge => 'Jembatan gigi',
            self::Denture => 'Gigi tiruan',
            self::Aligner => 'Aligner',
            self::Veneer => 'Veneer',
            self::Other => 'Lainnya',
        };
    }
}
