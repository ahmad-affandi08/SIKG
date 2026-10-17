<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Backup;

use DateTimeImmutable;

/**
 * Aturan retensi cadangan (docs/operations.md bagian 3): semua cadangan selama 30 hari terakhir, cadangan tertua tiap bulan
 * selama 12 bulan terakhir, dan cadangan tertua tiap tahun disimpan terus (kewajiban retensi rekam medis 25 tahun).
 * Cadangan terbaru tidak pernah dibuang. Fungsi murni: hanya menentukan nama yang boleh dibuang.
 */
final class BackupRetention
{
    public const DAILY_DAYS = 30;

    public const MONTHLY_MONTHS = 12;

    /**
     * @param  array<string, DateTimeImmutable>  $backups  nama berkas => waktu pembuatan (UTC)
     * @return list<string> nama yang boleh dibuang
     */
    public static function expired(array $backups, DateTimeImmutable $now): array
    {
        if ($backups === []) {
            return [];
        }
        asort($backups);

        $keep = [];
        $dailyFrom = $now->modify('-'.self::DAILY_DAYS.' days');
        $monthlyFrom = $now->modify('first day of this month midnight')->modify('-'.(self::MONTHLY_MONTHS - 1).' months');
        $seenMonth = [];
        $seenYear = [];

        foreach ($backups as $name => $at) {
            if ($at >= $dailyFrom) {
                $keep[$name] = true;
            }
            $year = $at->format('Y');
            if (! isset($seenYear[$year])) {
                $seenYear[$year] = true;
                $keep[$name] = true;
            }
            $month = $at->format('Y-m');
            if (! isset($seenMonth[$month])) {
                $seenMonth[$month] = true;
                if ($at >= $monthlyFrom) {
                    $keep[$name] = true;
                }
            }
        }
        $keep[array_key_last($backups)] = true;

        return array_values(array_filter(array_keys($backups), fn (string $name) => ! isset($keep[$name])));
    }
}
