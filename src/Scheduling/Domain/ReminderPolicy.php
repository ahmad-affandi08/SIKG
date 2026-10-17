<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Kapan pengingat janji (JDW-07) jatuh tempo, menurut waktu lokal klinik. Pengingat sehari sebelumnya (h1) mulai dikirim
 * pukul 09.00 hari sebelum kunjungan; pengingat hari kunjungan (h0) mulai pukul 07.00 dan hanya sebelum janji dimulai.
 * Mulai-kirim, bukan jam tetap, supaya tahan cron yang terlewat; pencatatan per janji mencegah kirim ganda.
 */
final class ReminderPolicy
{
    public const KIND_DAY_BEFORE = 'h1';

    public const KIND_SAME_DAY = 'h0';

    public const MAX_ATTEMPTS = 3;

    private const DAY_BEFORE_FROM_HOUR = 9;

    private const SAME_DAY_FROM_HOUR = 7;

    /**
     * @param  DateTimeImmutable  $startLocal  mulai janji, zona waktu klinik
     * @param  DateTimeImmutable  $nowLocal  sekarang, zona waktu klinik
     * @return 'h1'|'h0'|null
     */
    public static function due(DateTimeImmutable $startLocal, DateTimeImmutable $nowLocal): ?string
    {
        if ($startLocal <= $nowLocal) {
            return null;
        }

        $hour = (int) $nowLocal->format('G');
        $today = $nowLocal->format('Y-m-d');
        $startDay = $startLocal->format('Y-m-d');

        if ($startDay === $today) {
            return $hour >= self::SAME_DAY_FROM_HOUR ? self::KIND_SAME_DAY : null;
        }
        if ($startDay === $nowLocal->modify('+1 day')->format('Y-m-d')) {
            return $hour >= self::DAY_BEFORE_FROM_HOUR ? self::KIND_DAY_BEFORE : null;
        }

        return null;
    }

    public static function message(string $kind, string $clinicName, DateTimeImmutable $startLocal, string $doctorName): string
    {
        $when = $kind === self::KIND_SAME_DAY
            ? 'hari ini pukul '.$startLocal->format('H:i')
            : 'besok, '.$startLocal->format('d/m/Y').' pukul '.$startLocal->format('H:i');

        return "Pengingat dari {$clinicName}: Anda memiliki janji kunjungan {$when} dengan {$doctorName}. Bila perlu mengubah jadwal, mohon hubungi klinik.";
    }
}
