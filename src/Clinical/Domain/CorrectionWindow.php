<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Aturan kunci catatan (RM-07, M6, Permenkes 24/2022 Pasal 30). Selama kunjungan berjalan, catatan dapat diubah petugas
 * yang berhak menulis (setiap versi tersimpan). Setelah kunjungan ditutup, perbaikan langsung hanya oleh pengguna
 * berwenang dalam 2x24 jam sejak catatan diinput; selebihnya hanya lewat addendum.
 */
final class CorrectionWindow
{
    public const HOURS = 48;

    public static function allowsEdit(bool $encounterClosed, DateTimeImmutable $inputAt, DateTimeImmutable $now, bool $mayCorrectClosed): bool
    {
        if (! $encounterClosed) {
            return true;
        }

        return $mayCorrectClosed && $now <= $inputAt->modify('+'.self::HOURS.' hours');
    }
}
