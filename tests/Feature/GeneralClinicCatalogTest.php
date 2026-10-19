<?php

declare(strict_types=1);

use Database\Seeders\StarterDataSeeder;
use Illuminate\Support\Facades\DB;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Clinic\Infrastructure\Seeders\ProcedureSeeder;
use Src\Clinical\Domain\DocumentDefaults;
use Src\Clinical\Domain\DocumentType;
use Src\Clinical\Infrastructure\Reference\Icd10GeneralCatalog;
use Src\Pharmacy\Infrastructure\Seeders\MedicineSeeder;
use Src\Shared\Domain\ServiceProfile;

function klinikUmum(string $nama = 'Klinik Umum Uji'): ClinicModel
{
    $klinik = makeClinic($nama);
    $klinik->forceFill(['settings' => $klinik->clinicSettings()->withServiceProfile(ServiceProfile::General)->toArray()])->save();

    return $klinik;
}

it('KLU-03 data awal klinik umum berisi tindakan dan obat umum, bukan katalog gigi', function () {
    $umum = klinikUmum();
    $gigi = makeClinic('Klinik Gigi Uji');

    $this->seed(StarterDataSeeder::class);

    $kodeUmum = DB::table('procedures')->where('clinic_id', $umum->id)->pluck('code')->all();
    expect($kodeUmum)->toHaveCount(count(ProcedureSeeder::generalCatalog()))->toContain('KONSUL', 'SIRKUMSISI', 'EKG', 'LAB-GDS')
        ->not->toContain('SCALING', 'CABUT-DEWASA', 'PSA')
        ->and(DB::table('procedures')->where('clinic_id', $umum->id)->where('scope', '!=', 'visit')->count())->toBe(0)
        ->and(DB::table('medicines')->where('clinic_id', $umum->id)->count())->toBe(count(MedicineSeeder::generalCatalog()))
        ->and(DB::table('medicines')->where('clinic_id', $umum->id)->where('name', 'Amlodipin 5 mg')->exists())->toBeTrue()
        ->and(DB::table('medicines')->where('clinic_id', $umum->id)->where('name', 'Bahan tambal komposit')->exists())->toBeFalse();

    // Klinik gigi tidak berubah.
    expect(DB::table('procedures')->where('clinic_id', $gigi->id)->count())->toBe(count(ProcedureSeeder::catalog()))
        ->and(DB::table('medicines')->where('clinic_id', $gigi->id)->count())->toBe(count(MedicineSeeder::catalog()));
});

it('KLU-03 data awal klinik umum nonaktif dengan harga 0 dan aman dijalankan berulang', function () {
    $umum = klinikUmum();
    $this->seed(StarterDataSeeder::class);

    expect(DB::table('procedures')->where('clinic_id', $umum->id)->where(fn ($q) => $q->where('is_active', true)->orWhere('default_price_amount', '>', 0))->count())->toBe(0)
        ->and(DB::table('medicines')->where('clinic_id', $umum->id)->where(fn ($q) => $q->where('is_active', true)->orWhere('sell_price_amount', '>', 0))->count())->toBe(0);

    DB::table('procedures')->where('clinic_id', $umum->id)->where('code', 'KONSUL')->update(['default_price_amount' => 75000, 'is_active' => true]);
    $sebelum = [DB::table('procedures')->count(), DB::table('medicines')->count()];

    $this->seed(StarterDataSeeder::class);

    expect([DB::table('procedures')->count(), DB::table('medicines')->count()])->toBe($sebelum)
        ->and((int) DB::table('procedures')->where('clinic_id', $umum->id)->where('code', 'KONSUL')->value('default_price_amount'))->toBe(75000);
});

it('KLU-03 katalog umum konsisten: kode dan nama tidak ganda, kelas obat sah, kode ICD-9-CM berbentuk benar', function () {
    $kode = array_column(ProcedureSeeder::generalCatalog(), 'code');
    $nama = array_map('mb_strtolower', array_column(MedicineSeeder::generalCatalog(), 'name'));

    expect($kode)->toBe(array_values(array_unique($kode)))->and($nama)->toBe(array_values(array_unique($nama)));
    foreach (ProcedureSeeder::generalCatalog() as $baris) {
        expect($baris['scope'])->toBe('visit');
        if ($baris['icd9'] !== null) {
            expect($baris['icd9'])->toMatch('/^\d{2}\.\d{1,2}$/');
        }
    }
    foreach (MedicineSeeder::generalCatalog() as $obat) {
        if ($obat['drug_class'] !== null) {
            expect($obat['drug_class'])->toMatch('/^[a-z0-9_]{1,60}$/');
        }
        expect(mb_strlen($obat['name']))->toBeLessThanOrEqual(150);
    }
    foreach (Icd10GeneralCatalog::all() as $kodeIcd => $namaIcd) {
        expect($kodeIcd)->toMatch('/^[A-Z]\d{2}(\.\d)?$/')->and(mb_strlen($namaIcd))->toBeGreaterThan(3);
    }
});

it('KLU-03 templat bawaan klinik umum tanpa kata gigi, rujukan sama, dan klinik gigi tetap memakai templat gigi', function () {
    foreach ([DocumentType::Consent, DocumentType::Certificate] as $jenis) {
        expect(mb_strtolower(DocumentDefaults::body($jenis, ServiceProfile::General)))->not->toContain('gigi')
            ->and(mb_strtolower(DocumentDefaults::body($jenis)))->toContain('gigi');
    }
    expect(DocumentDefaults::body(DocumentType::Referral, ServiceProfile::General))->toBe(DocumentDefaults::body(DocumentType::Referral))
        ->and(DocumentDefaults::body(DocumentType::Certificate, ServiceProfile::General))->toContain('{{istirahat}}')->toContain('{{diagnosa}}');
});
