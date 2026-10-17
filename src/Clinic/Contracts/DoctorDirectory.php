<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kontrak daftar dokter dan jadwal praktiknya untuk konteks lain (Scheduling, Clinical).
 */
interface DoctorDirectory
{
    public function find(string $doctorId): ?DoctorSummary;

    /**
     * @return list<DoctorSummary> dokter aktif, urut nama
     */
    public function active(): array;

    /**
     * @param  list<string>  $ids
     * @return array<string, DoctorSummary> termasuk dokter nonaktif (riwayat tetap menampilkan namanya)
     */
    public function byIds(array $ids): array;

    /**
     * Jadwal praktik mingguan (jam lokal klinik): weekday 1 = Senin ... 7 = Minggu.
     *
     * @return list<array{weekday: int, start: string, end: string}>
     */
    public function weeklySchedule(string $doctorId): array;
}
