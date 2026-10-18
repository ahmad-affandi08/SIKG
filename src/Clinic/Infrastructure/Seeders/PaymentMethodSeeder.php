<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Metode pembayaran awal (MST-06): tunai, transfer bank, QRIS, kartu debit, dan kartu kredit, masing-masing bila klinik
 * belum punya metode dengan jenis itu. Pencatatan manual. Kasir tidak dapat menerima pembayaran sebelum ada metode,
 * dan klinik baru tidak membuatnya sendiri. Aman dijalankan berulang.
 */
final class PaymentMethodSeeder extends Seeder
{
    /** @return array<string, string> jenis => nama */
    public static function methods(): array
    {
        return ['cash' => 'Tunai', 'transfer' => 'Transfer bank', 'qris' => 'QRIS', 'debit' => 'Kartu debit', 'credit' => 'Kartu kredit'];
    }

    public function run(): void
    {
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $this->seedClinic((string) $clinicId);
        }
    }

    /** @return int jumlah metode yang ditambahkan */
    public function seedClinic(string $clinicId): int
    {
        $types = DB::table('payment_methods')->where('clinic_id', $clinicId)->pluck('type')->all();
        $added = 0;
        $now = now();
        foreach (self::methods() as $type => $name) {
            if (in_array($type, $types, true)) {
                continue;
            }
            DB::table('payment_methods')->insert([
                'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $clinicId, 'name' => $name, 'type' => $type, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        return $added;
    }
}
