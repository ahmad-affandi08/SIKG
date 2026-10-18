<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Application\Setup\StarterDataContributor;

/**
 * Kategori biaya operasional awal (P2-08) untuk setiap klinik. Tanpa kategori, halaman Catat biaya tidak dapat dipakai.
 * Aman dijalankan berulang: kategori yang namanya sudah ada tidak diduplikasi, dan yang diubah atau dinonaktifkan klinik tidak ditimpa.
 */
final class ExpenseCategorySeeder extends Seeder implements StarterDataContributor
{
    /** @return list<string> */
    public static function names(): array
    {
        return [
            'Jasa dan gaji',
            'Sewa tempat',
            'Listrik, air, dan internet',
            'Bahan habis pakai',
            'Biaya lab gigi',
            'Perawatan dan servis alat',
            'Pemasaran',
            'Perizinan dan pajak',
            'Lain-lain',
        ];
    }

    public function starterKey(): string
    {
        return 'expense_categories';
    }

    public function run(): void
    {
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $this->seedClinic((string) $clinicId);
        }
    }

    /** @return int jumlah kategori yang ditambahkan */
    public function seedClinic(string $clinicId): int
    {
        $existing = DB::table('expense_categories')->where('clinic_id', $clinicId)->pluck('name')
            ->map(fn ($n) => mb_strtolower((string) $n))->all();
        $added = 0;
        $now = now();
        foreach (self::names() as $name) {
            if (in_array(mb_strtolower($name), $existing, true)) {
                continue;
            }
            DB::table('expense_categories')->insert([
                'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $clinicId, 'name' => $name, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        return $added;
    }
}
