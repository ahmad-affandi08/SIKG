<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

/**
 * Aturan jadwal opname: jatuh tempo berikutnya dihitung dari hari sesi dibuat, bukan dari hari sesi selesai, agar jadwal
 * tidak bergeser ketika penghitungan terlambat.
 */
final class StockCountPolicy
{
    public const MIN_INTERVAL_DAYS = 7;

    public const MAX_INTERVAL_DAYS = 365;

    public static function validInterval(int $days): bool
    {
        return $days >= self::MIN_INTERVAL_DAYS && $days <= self::MAX_INTERVAL_DAYS;
    }

    /**
     * @param  string  $from  tanggal YYYY-MM-DD
     */
    public static function nextDue(string $from, int $intervalDays): string
    {
        return (new DateTimeImmutable($from))->modify('+'.$intervalDays.' days')->format('Y-m-d');
    }

    public static function isDue(?string $nextDue, string $today): bool
    {
        return $nextDue === null || $nextDue <= $today;
    }
}
