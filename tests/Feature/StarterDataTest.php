<?php

declare(strict_types=1);

use Database\Seeders\StarterDataSeeder;
use Illuminate\Support\Facades\DB;
use Src\Clinic\Infrastructure\Seeders\NationalHolidaySeeder;
use Src\Clinic\Infrastructure\Seeders\ProcedureSeeder;
use Src\Pharmacy\Infrastructure\Seeders\MedicineSeeder;

it('DATA-AWAL membuat kategori biaya, lima metode bayar, tindakan, obat, dan libur tetap untuk klinik baru', function () {
    $klinik = makeClinic('Klinik Data Awal');

    $this->seed(StarterDataSeeder::class);

    $id = $klinik->id;
    expect(DB::table('expense_categories')->where('clinic_id', $id)->count())->toBe(9)
        ->and(DB::table('payment_methods')->where('clinic_id', $id)->pluck('type')->sort()->values()->all())
        ->toBe(['cash', 'credit', 'debit', 'qris', 'transfer'])
        ->and(DB::table('procedures')->where('clinic_id', $id)->count())->toBe(count(ProcedureSeeder::catalog()))
        ->and(DB::table('medicines')->where('clinic_id', $id)->count())->toBe(count(MedicineSeeder::catalog()))
        ->and(DB::table('holidays')->where('clinic_id', $id)->count())->toBe(2 * count(NationalHolidaySeeder::fixed()));
});

it('DATA-AWAL tindakan dan obat dibuat nonaktif dengan harga 0 sampai klinik mengisinya', function () {
    $klinik = makeClinic();
    $this->seed(StarterDataSeeder::class);

    expect(DB::table('procedures')->where('clinic_id', $klinik->id)->where(fn ($q) => $q->where('is_active', true)->orWhere('default_price_amount', '>', 0))->count())->toBe(0)
        ->and(DB::table('medicines')->where('clinic_id', $klinik->id)->where(fn ($q) => $q->where('is_active', true)->orWhere('sell_price_amount', '>', 0))->count())->toBe(0);
});

it('DATA-AWAL aman dijalankan berulang dan tidak menimpa yang sudah diubah klinik', function () {
    $klinik = makeClinic();
    $this->seed(StarterDataSeeder::class);
    DB::table('procedures')->where('clinic_id', $klinik->id)->where('code', 'SCALING')
        ->update(['default_price_amount' => 250000, 'is_active' => true]);
    $sebelum = [
        DB::table('procedures')->where('clinic_id', $klinik->id)->count(),
        DB::table('medicines')->where('clinic_id', $klinik->id)->count(),
        DB::table('expense_categories')->where('clinic_id', $klinik->id)->count(),
        DB::table('holidays')->where('clinic_id', $klinik->id)->count(),
        DB::table('payment_methods')->where('clinic_id', $klinik->id)->count(),
    ];

    $this->seed(StarterDataSeeder::class);

    $sesudah = [
        DB::table('procedures')->where('clinic_id', $klinik->id)->count(),
        DB::table('medicines')->where('clinic_id', $klinik->id)->count(),
        DB::table('expense_categories')->where('clinic_id', $klinik->id)->count(),
        DB::table('holidays')->where('clinic_id', $klinik->id)->count(),
        DB::table('payment_methods')->where('clinic_id', $klinik->id)->count(),
    ];
    $scaling = DB::table('procedures')->where('clinic_id', $klinik->id)->where('code', 'SCALING')->first();
    expect($sesudah)->toBe($sebelum)
        ->and((int) $scaling->default_price_amount)->toBe(250000)
        ->and((bool) $scaling->is_active)->toBeTrue();
});

it('DATA-AWAL berlaku untuk setiap klinik tanpa bocor antar klinik', function () {
    $a = makeClinic('Klinik A');
    $b = makeClinic('Klinik B');

    $this->seed(StarterDataSeeder::class);

    foreach ([$a, $b] as $k) {
        expect(DB::table('procedures')->where('clinic_id', $k->id)->count())->toBe(count(ProcedureSeeder::catalog()));
    }
    expect(DB::table('procedures')->count())->toBe(2 * count(ProcedureSeeder::catalog()));
});

it('DATA-AWAL kode tindakan valid dan kondisi gigi hasil tindakan ada di klinik', function () {
    $klinik = makeClinic();
    $this->seed(StarterDataSeeder::class);

    $kondisi = DB::table('tooth_conditions')->where('clinic_id', $klinik->id)->pluck('code')->all();
    expect($kondisi)->not->toBeEmpty();
    $kode = [];
    foreach (DB::table('procedures')->where('clinic_id', $klinik->id)->get() as $p) {
        $kode[] = $p->code;
        expect($p->code)->toMatch('/^[A-Za-z0-9._-]+$/')
            ->and($p->scope)->toBeIn(['tooth', 'surface', 'jaw', 'visit']);
        if ($p->icd9_code !== null) {
            expect($p->icd9_code)->toMatch('/^\d{1,2}(\.\d{1,2})?$/');
        }
        if ($p->result_condition_code !== null) {
            expect($kondisi)->toContain($p->result_condition_code);
        }
    }
    expect($kode)->toBe(array_values(array_unique($kode)));
});

it('DATA-AWAL libur tetap mencakup tahun berjalan dan tahun depan dan menyusul bila tahun berganti', function () {
    $klinik = makeClinic();
    $seeder = new NationalHolidaySeeder;

    $seeder->seedClinic($klinik->id, new DateTimeImmutable('2030-03-10'));
    $tanggal = DB::table('holidays')->where('clinic_id', $klinik->id)->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
    expect($tanggal)->toContain('2030-08-17', '2031-12-25', '2030-01-01');

    $seeder->seedClinic($klinik->id, new DateTimeImmutable('2031-01-02'));
    $tanggal = DB::table('holidays')->where('clinic_id', $klinik->id)->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->all();
    expect($tanggal)->toContain('2032-05-01')->and(count($tanggal))->toBe(3 * count(NationalHolidaySeeder::fixed()));
});

it('DATA-AWAL tanpa klinik tidak melakukan apa pun', function () {
    $this->seed(StarterDataSeeder::class);

    expect(DB::table('procedures')->count())->toBe(0)->and(DB::table('medicines')->count())->toBe(0);
});

it('DATA-AWAL kelas obat mengikuti aturan formulir (huruf kecil, angka, garis bawah) sehingga obat bawaan dapat diubah', function () {
    foreach (MedicineSeeder::catalog() as $obat) {
        if ($obat['drug_class'] !== null) {
            expect($obat['drug_class'])->toMatch('/^[a-z0-9_]+$/');
        }
    }
});
