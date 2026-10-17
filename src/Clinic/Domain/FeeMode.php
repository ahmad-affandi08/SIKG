<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Cara menghitung jasa dokter: persentase dari tarif (basis poin) atau nominal tetap (rupiah) per tindakan.
 */
enum FeeMode: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Persentase dari tarif',
            self::Fixed => 'Nominal tetap per tindakan',
        };
    }
}
