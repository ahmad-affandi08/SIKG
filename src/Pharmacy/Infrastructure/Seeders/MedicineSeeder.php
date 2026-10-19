<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Clinic\Contracts\ServiceProfileLookup;
use Src\Shared\Application\Setup\StarterDataContributor;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\ServiceProfile;

/**
 * Katalog obat dan bahan umum untuk klinik gigi (MST-05) sebagai usulan awal. Semua baris dibuat NONAKTIF dengan harga 0 dan
 * stok minimum 0: klinik wajib mengisi harga jual, lalu mengaktifkannya, sebelum obat dapat diresepkan atau ditagih. Kelas obat
 * (dasar peringatan alergi, OBT-02; huruf kecil, angka, garis bawah sesuai aturan formulir) wajib diperiksa dokter klinik. Aman dijalankan berulang: nama yang sudah ada tidak diduplikasi.
 * Klinik berprofil umum (KLU-01) mendapat {@see generalCatalog()}; klinik gigi mendapat {@see catalog()}.
 */
final class MedicineSeeder extends Seeder implements StarterDataContributor
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

    /**
     * Obat dan bahan awal klinik umum (KLU-03): obat layanan primer yang lazim. Nama, satuan, dan kelas wajib diperiksa dokter dan
     * apoteker klinik; harga sengaja 0 dan semua baris nonaktif sampai klinik mengisinya.
     *
     * @return list<array{name: string, unit: string, drug_class: ?string}>
     */
    public static function generalCatalog(): array
    {
        $m = fn (string $name, string $unit, ?string $class = null): array => ['name' => $name, 'unit' => $unit, 'drug_class' => $class];

        return [
            // Antibiotik
            $m('Amoksisilin 500 mg', 'kapsul', 'penisilin'),
            $m('Amoksisilin sirup 125 mg/5 ml', 'botol', 'penisilin'),
            $m('Kotrimoksazol 480 mg', 'tablet', 'sulfonamida'),
            $m('Siprofloksasin 500 mg', 'tablet', 'kuinolon'),
            $m('Sefiksim 100 mg', 'kapsul', 'sefalosporin'),
            $m('Sefadroksil 500 mg', 'kapsul', 'sefalosporin'),
            $m('Azitromisin 500 mg', 'tablet', 'makrolida'),
            $m('Doksisiklin 100 mg', 'kapsul', 'tetrasiklin'),
            $m('Metronidazol 500 mg', 'tablet', 'nitroimidazol'),
            $m('Seftriakson 1 g injeksi', 'vial', 'sefalosporin'),
            // Pereda nyeri, demam, dan antiradang
            $m('Parasetamol 500 mg', 'tablet', 'parasetamol'),
            $m('Parasetamol sirup 120 mg/5 ml', 'botol', 'parasetamol'),
            $m('Ibuprofen 400 mg', 'tablet', 'nsaid'),
            $m('Asam mefenamat 500 mg', 'tablet', 'nsaid'),
            $m('Natrium diklofenak 50 mg', 'tablet', 'nsaid'),
            $m('Meloksikam 15 mg', 'tablet', 'nsaid'),
            $m('Diklofenak injeksi 75 mg', 'ampul', 'nsaid'),
            // Saluran napas dan alergi
            $m('Salbutamol 2 mg', 'tablet', 'bronkodilator'),
            $m('Ambroksol 30 mg', 'tablet', 'mukolitik'),
            $m('Gliseril guaiakolat 100 mg', 'tablet', 'ekspektoran'),
            $m('Loratadin 10 mg', 'tablet', 'antihistamin'),
            $m('Setirizin 10 mg', 'tablet', 'antihistamin'),
            $m('Klorfeniramin maleat (CTM) 4 mg', 'tablet', 'antihistamin'),
            $m('Deksametason 0,5 mg', 'tablet', 'kortikosteroid'),
            $m('Prednison 5 mg', 'tablet', 'kortikosteroid'),
            $m('Deksametason injeksi 5 mg/ml', 'ampul', 'kortikosteroid'),
            // Pencernaan
            $m('Antasida tablet kunyah', 'tablet', 'antasida'),
            $m('Omeprazol 20 mg', 'kapsul', 'ppi'),
            $m('Domperidon 10 mg', 'tablet', 'prokinetik'),
            $m('Ondansetron 4 mg', 'tablet', 'antiemetik'),
            $m('Oralit', 'sachet', null),
            $m('Zink 20 mg', 'tablet', null),
            // Jantung, tekanan darah, dan metabolik
            $m('Amlodipin 5 mg', 'tablet', 'ccb'),
            $m('Amlodipin 10 mg', 'tablet', 'ccb'),
            $m('Kaptopril 25 mg', 'tablet', 'ace_inhibitor'),
            $m('Hidroklorotiazid 25 mg', 'tablet', 'diuretik'),
            $m('Furosemid 40 mg', 'tablet', 'diuretik'),
            $m('Bisoprolol 5 mg', 'tablet', 'beta_blocker'),
            $m('Metformin 500 mg', 'tablet', 'biguanid'),
            $m('Glibenklamid 5 mg', 'tablet', 'sulfonilurea'),
            $m('Simvastatin 20 mg', 'tablet', 'statin'),
            $m('Alopurinol 100 mg', 'tablet', 'xantin_oksidase_inhibitor'),
            // Vitamin dan mineral
            $m('Vitamin B kompleks', 'tablet', null),
            $m('Vitamin C 50 mg', 'tablet', null),
            $m('Asam folat 1 mg', 'tablet', null),
            $m('Tablet tambah darah (zat besi dan asam folat)', 'tablet', null),
            // Kulit, mata, dan telinga
            $m('Hidrokortison krim 2,5%', 'tube', 'kortikosteroid'),
            $m('Betametason krim 0,1%', 'tube', 'kortikosteroid'),
            $m('Mikonazol krim 2%', 'tube', 'azol'),
            $m('Ketokonazol 200 mg', 'tablet', 'azol'),
            $m('Permetrin krim 5%', 'tube', null),
            $m('Gentamisin salep 0,1%', 'tube', 'aminoglikosida'),
            $m('Salep 2-4 (asam salisilat dan belerang)', 'pot', null),
            $m('Kloramfenikol tetes mata', 'botol', 'kloramfenikol'),
            $m('Kloramfenikol salep mata', 'tube', 'kloramfenikol'),
            // Anestesi dan cairan
            $m('Lidokain 2% injeksi', 'ampul', 'anestesi_lokal'),
            $m('Natrium klorida 0,9% infus', 'kantong', null),
            $m('Ringer laktat infus', 'kantong', null),
            $m('Dekstrosa 5% infus', 'kantong', null),
            // Antiseptik dan bahan habis pakai
            $m('Alkohol 70%', 'botol', null),
            $m('Povidone iodine 10%', 'botol', 'iodium'),
            $m('Kasa steril', 'bungkus', null),
            $m('Plester', 'roll', null),
            $m('Spuit sekali pakai 3 ml', 'buah', null),
            $m('Sarung tangan periksa', 'kotak', null),
            $m('Masker bedah', 'kotak', null),
        ];
    }

    /** @return list<array{name: string, unit: string, drug_class: ?string}> */
    public static function catalogFor(ServiceProfile $profile): array
    {
        return $profile->dental() ? self::catalog() : self::generalCatalog();
    }

    public function starterKey(): string
    {
        return 'medicines';
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
        // Profil layanan dibaca lewat kontrak Clinic sebagai klinik yang bersangkutan (seeder berjalan tanpa pengguna).
        $profile = app(TenantContext::class)->runAs($clinicId, null, fn (): ServiceProfile => app(ServiceProfileLookup::class)->current());
        $existing = DB::table('medicines')->where('clinic_id', $clinicId)->pluck('name')
            ->map(fn ($n) => mb_strtolower((string) $n))->all();
        $added = 0;
        $now = now();
        foreach (self::catalogFor($profile) as $item) {
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
