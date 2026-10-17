<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

enum CaseStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Berjalan',
            self::Completed => 'Selesai',
            self::Stopped => 'Dihentikan',
        };
    }
}
