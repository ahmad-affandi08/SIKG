<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Gigi dengan penomoran FDI (M8): dewasa 11-18, 21-28, 31-38, 41-48 (32 gigi) dan sulung 51-55, 61-65, 71-75, 81-85 (20 gigi).
 */
final readonly class Tooth
{
    public int $fdi;

    public function __construct(int $fdi)
    {
        $quadrant = intdiv($fdi, 10);
        $position = $fdi % 10;
        $permanent = $quadrant >= 1 && $quadrant <= 4 && $position >= 1 && $position <= 8;
        $primary = $quadrant >= 5 && $quadrant <= 8 && $position >= 1 && $position <= 5;
        if (! $permanent && ! $primary) {
            throw new InvalidClinicalData('clinical.invalid_tooth', 'Nomor gigi tidak valid menurut penomoran FDI.');
        }
        $this->fdi = $fdi;
    }

    public function dentition(): Dentition
    {
        return intdiv($this->fdi, 10) <= 4 ? Dentition::Permanent : Dentition::Primary;
    }

    public function isUpper(): bool
    {
        return in_array(intdiv($this->fdi, 10), [1, 2, 5, 6], true);
    }

    /**
     * Gigi depan (insisivus dan kaninus) memakai tepi insisal, bukan permukaan oklusal.
     */
    public function isAnterior(): bool
    {
        return $this->fdi % 10 <= 3;
    }

    /**
     * Seluruh gigi yang sah, menurut urutan tampilan odontogram (kanan pasien ke kiri pasien, atas lalu bawah).
     *
     * @return list<int>
     */
    public static function all(Dentition $dentition): array
    {
        $permanent = array_merge(range(18, 11), range(21, 28), range(48, 41), range(31, 38));
        $primary = array_merge(range(55, 51), range(61, 65), range(85, 81), range(71, 75));

        return match ($dentition) {
            Dentition::Permanent => $permanent,
            Dentition::Primary => $primary,
            Dentition::Mixed => array_merge($permanent, $primary),
        };
    }
}
