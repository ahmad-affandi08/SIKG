<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

/**
 * OBT-06: kapan stok dan batch perlu diperingatkan. Minimum 0 berarti obat itu tidak dipantau; kedaluwarsa dihitung 30 hari
 * ke depan dan batch yang sudah lewat tetap ditampilkan selama obatnya masih ada stok.
 */
final class StockAlertPolicy
{
    public const EXPIRY_WINDOW_DAYS = 30;

    /** Jam lokal paling awal pemberitahuan harian diterbitkan, agar tidak membangunkan staf tengah malam. */
    public const ALERT_FROM_HOUR = 7;

    public static function isLow(int $quantity, int $minStock): bool
    {
        return $minStock > 0 && $quantity < $minStock;
    }

    public static function expiryLimit(DateTimeImmutable $today): string
    {
        return $today->modify('+'.self::EXPIRY_WINDOW_DAYS.' days')->format('Y-m-d');
    }

    /** Sisa hari sampai tanggal kedaluwarsa; negatif bila sudah lewat. */
    public static function daysLeft(DateTimeImmutable $today, string $expiry): int
    {
        return (int) $today->setTime(0, 0)->diff(new DateTimeImmutable($expiry))->format('%r%a');
    }
}
