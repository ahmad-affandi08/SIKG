<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

enum EncounterStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Berjalan',
            self::Closed => 'Ditutup',
        };
    }
}
