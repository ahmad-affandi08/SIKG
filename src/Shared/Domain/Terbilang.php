<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use InvalidArgumentException;

/**
 * Angka rupiah dalam kata ("terbilang") untuk kwitansi dan invoice: 1.250.000 menjadi "satu juta dua ratus lima puluh ribu rupiah".
 * Hanya bilangan bulat tidak negatif (uang selalu bilangan bulat rupiah, M4).
 */
final class Terbilang
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    private const SCALES = [1_000_000_000_000 => 'triliun', 1_000_000_000 => 'miliar', 1_000_000 => 'juta', 1_000 => 'ribu'];

    public static function rupiah(int $amount): string
    {
        return self::words($amount).' rupiah';
    }

    public static function words(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Terbilang hanya untuk bilangan tidak negatif.');
        }
        if ($amount === 0) {
            return 'nol';
        }

        return trim((string) preg_replace('/\s+/', ' ', self::compose($amount)));
    }

    private static function compose(int $n): string
    {
        if ($n < 12) {
            return self::UNITS[$n];
        }
        if ($n < 20) {
            return self::UNITS[$n - 10].' belas';
        }
        if ($n < 100) {
            return self::UNITS[intdiv($n, 10)].' puluh '.self::compose($n % 10);
        }
        if ($n < 200) {
            return 'seratus '.self::compose($n - 100);
        }
        if ($n < 1000) {
            return self::UNITS[intdiv($n, 100)].' ratus '.self::compose($n % 100);
        }
        foreach (self::SCALES as $value => $name) {
            if ($n >= $value) {
                $head = intdiv($n, $value);
                $lead = $value === 1000 && $head === 1 ? 'seribu' : self::compose($head).' '.$name;

                return $lead.' '.self::compose($n % $value);
            }
        }

        return '';
    }
}
