<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Src\Reporting\Domain\ColumnKind;

/**
 * Format tampilan sel laporan untuk PDF (M18): uang "Rp 1.250.000", tanggal DD/MM/YYYY, persen dengan koma desimal.
 * Nilai dasarnya tetap bilangan bulat; hanya penulisan yang berubah.
 */
final class CellFormatter
{
    public static function text(ColumnKind $kind, int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($kind) {
            ColumnKind::Text => (string) $value,
            ColumnKind::Integer => self::number((int) $value),
            ColumnKind::Money => (is_int($value) && $value < 0 ? '-' : '').'Rp '.self::number(abs((int) $value)),
            ColumnKind::Percent => self::percent((int) $value),
            ColumnKind::Minutes => self::number((int) $value).' mnt',
            ColumnKind::Date => self::date((string) $value),
            ColumnKind::DateTime => self::dateTime((string) $value),
        };
    }

    public static function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /** Basis poin ke persen: 8550 menjadi "85,5%", 10000 menjadi "100%". */
    public static function percent(int $basisPoints): string
    {
        $sign = $basisPoints < 0 ? '-' : '';
        $abs = abs($basisPoints);
        $whole = intdiv($abs, 100);
        $frac = $abs % 100;

        return $sign.$whole.($frac === 0 ? '' : ','.rtrim(sprintf('%02d', $frac), '0')).'%';
    }

    public static function date(string $value): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1 ? "{$m[3]}/{$m[2]}/{$m[1]}" : $value;
    }

    public static function dateTime(string $value): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $value, $m) === 1 ? "{$m[3]}/{$m[2]}/{$m[1]} {$m[4]}:{$m[5]}" : $value;
    }

    public static function isNumeric(ColumnKind $kind): bool
    {
        return in_array($kind, [ColumnKind::Integer, ColumnKind::Money, ColumnKind::Percent, ColumnKind::Minutes], true);
    }
}
