<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Zona waktu dan hari libur klinik. Basis data menyimpan UTC; jam praktik dan tanggal antrean mengikuti zona waktu klinik.
 */
interface ClinicCalendar
{
    public function timezone(): string;

    /**
     * @param  string  $date  tanggal lokal klinik YYYY-MM-DD
     */
    public function isHoliday(string $date): bool;
}
