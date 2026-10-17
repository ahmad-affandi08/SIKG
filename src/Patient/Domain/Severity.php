<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

enum Severity: string
{
    case Mild = 'mild';
    case Moderate = 'moderate';
    case Severe = 'severe';

    public function label(): string
    {
        return match ($this) {
            self::Mild => 'Ringan',
            self::Moderate => 'Sedang',
            self::Severe => 'Berat',
        };
    }
}
