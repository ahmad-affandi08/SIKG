<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Seeders;

use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hari libur nasional yang tanggalnya TETAP tiap tahun (Tahun Baru, Hari Buruh, Hari Lahir Pancasila, HUT Kemerdekaan, Natal)
 * untuk tahun berjalan dan tahun depan, agar kalender janji dan booking online menutup hari itu. Libur yang berpindah tiap tahun
 * (Idulfitri, Idul Adha, Imlek, Nyepi, Waisak, Kenaikan Isa Almasih, tahun baru Islam, Maulid, cuti bersama) berubah menurut
 * keputusan bersama menteri dan harus ditambahkan klinik lewat Pengaturan, Libur. Aman dijalankan berulang.
 */
final class NationalHolidaySeeder extends Seeder
{
    /** @return array<string, string> bulan-hari => nama */
    public static function fixed(): array
    {
        return [
            '01-01' => 'Tahun Baru Masehi',
            '05-01' => 'Hari Buruh Internasional',
            '06-01' => 'Hari Lahir Pancasila',
            '08-17' => 'Hari Kemerdekaan Republik Indonesia',
            '12-25' => 'Hari Raya Natal',
        ];
    }

    public function run(): void
    {
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $this->seedClinic((string) $clinicId);
        }
    }

    /** @return int jumlah hari libur yang ditambahkan */
    public function seedClinic(string $clinicId, ?DateTimeImmutable $today = null): int
    {
        $year = (int) ($today ?? new DateTimeImmutable('now'))->format('Y');
        $existing = DB::table('holidays')->where('clinic_id', $clinicId)->whereNull('deleted_at')->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $added = 0;
        $now = now();
        foreach ([$year, $year + 1] as $y) {
            foreach (self::fixed() as $monthDay => $name) {
                $date = "{$y}-{$monthDay}";
                if (in_array($date, $existing, true)) {
                    continue;
                }
                DB::table('holidays')->insert([
                    'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $clinicId, 'date' => $date, 'name' => $name,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $added++;
            }
        }

        return $added;
    }
}
