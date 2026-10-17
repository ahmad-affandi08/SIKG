<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

enum Jaw: string
{
    case Upper = 'upper';
    case Lower = 'lower';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Upper => 'Rahang atas',
            self::Lower => 'Rahang bawah',
            self::Both => 'Kedua rahang',
        };
    }
}
