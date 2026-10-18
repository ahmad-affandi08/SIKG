<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Katalog obat dan bahan umum untuk klinik gigi (MST-05) sebagai usulan awal. Semua baris dibuat NONAKTIF dengan harga 0 dan
 * stok minimum 0: klinik wajib mengisi harga jual, lalu mengaktifkannya, sebelum obat dapat diresepkan atau ditagih. Kelas obat
 * (dasar peringatan alergi, OBT-02; huruf kecil, angka, garis bawah sesuai aturan formulir) wajib diperiksa dokter klinik. Aman dijalankan berulang: nama yang sudah ada tidak diduplikasi.
 */
final class MedicineSeeder extends Seeder
{
    /**
     * @return list<array{name: string, unit: string, drug_class: ?string}>
     */
    public static function catalog(): array
    {
        return [
            // Antibiotik
            ['name' => 'Amoksisilin 500 mg', 'unit' => 'kapsul', 'drug_class' => 'penisilin'],
            ['name' => 'Metronidazol 500 mg', 'unit' => 'tablet', 'drug_class' => 'nitroimidazol'],
            ['name' => 'Klindamisin 300 mg', 'unit' => 'kapsul', 'drug_class' => 'linkosamida'],
            ['name' => 'Sefadroksil 500 mg', 'unit' => 'kapsul', 'drug_class' => 'sefalosporin'],
            ['name' => 'Azitromisin 500 mg', 'unit' => 'tablet', 'drug_class' => 'makrolida'],
            // Pereda nyeri dan antiradang
            ['name' => 'Parasetamol 500 mg', 'unit' => 'tablet', 'drug_class' => 'parasetamol'],
            ['name' => 'Asam mefenamat 500 mg', 'unit' => 'tablet', 'drug_class' => 'nsaid'],
            ['name' => 'Ibuprofen 400 mg', 'unit' => 'tablet', 'drug_class' => 'nsaid'],
            ['name' => 'Natrium diklofenak 50 mg', 'unit' => 'tablet', 'drug_class' => 'nsaid'],
            ['name' => 'Deksametason 0,5 mg', 'unit' => 'tablet', 'drug_class' => 'kortikosteroid'],
            // Antiseptik dan obat kumur
            ['name' => 'Klorheksidin 0,2% obat kumur', 'unit' => 'botol', 'drug_class' => 'klorheksidin'],
            ['name' => 'Povidone iodine 10%', 'unit' => 'botol', 'drug_class' => 'iodium'],
            // Anestesi dan bahan tindakan
            ['name' => 'Anestesi lokal injeksi (ampul)', 'unit' => 'ampul', 'drug_class' => 'anestesi_lokal'],
            ['name' => 'Anestesi topikal gel', 'unit' => 'tube', 'drug_class' => 'anestesi_lokal'],
            ['name' => 'Bahan tambal komposit', 'unit' => 'syringe', 'drug_class' => null],
            ['name' => 'Semen ionomer kaca (GIC)', 'unit' => 'set', 'drug_class' => null],
            ['name' => 'Semen tambal sementara', 'unit' => 'pot', 'drug_class' => null],
            ['name' => 'Kapas gulung', 'unit' => 'bungkus', 'drug_class' => null],
            ['name' => 'Sarung tangan periksa', 'unit' => 'kotak', 'drug_class' => null],
            ['name' => 'Masker bedah', 'unit' => 'kotak', 'drug_class' => null],
        ];
    }

    public function run(): void
    {
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $this->seedClinic((string) $clinicId);
        }
    }

    /** @return int jumlah obat atau bahan yang ditambahkan */
    public function seedClinic(string $clinicId): int
    {
        $existing = DB::table('medicines')->where('clinic_id', $clinicId)->pluck('name')
            ->map(fn ($n) => mb_strtolower((string) $n))->all();
        $added = 0;
        $now = now();
        foreach (self::catalog() as $item) {
            if (in_array(mb_strtolower($item['name']), $existing, true)) {
                continue;
            }
            DB::table('medicines')->insert([
                'id' => strtoupper((string) Str::ulid()), 'clinic_id' => $clinicId, 'name' => $item['name'], 'unit' => $item['unit'],
                'sell_price_amount' => 0, 'min_stock' => 0, 'drug_class' => $item['drug_class'], 'is_active' => false,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        return $added;
    }
}
