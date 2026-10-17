<?php

declare(strict_types=1);

use Src\Patient\Application\PatientDraft;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\MedicalHistoryEntry;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Domain\NameKey;
use Src\Patient\Domain\Nik;
use Src\Patient\Domain\PhoneNumber;

const HARI_INI = '2025-06-15';

function draft(array $o = []): PatientDraft
{
    return new PatientDraft(...[
        'name' => 'Budi Santoso',
        'nik' => '9999010101900001',
        'nikMissingReason' => null,
        'birthDate' => '1990-01-01',
        'sex' => 'male',
        ...$o,
    ]);
}

function hariIni(): DateTimeImmutable
{
    return new DateTimeImmutable(HARI_INI);
}

it('PAS-01 NIK harus 16 digit dan tidak boleh digit yang sama semua', function () {
    expect((new Nik('9999 0101 0190 0001'))->value)->toBe('9999010101900001');
    foreach (['123', '99990101019000012', 'abcdefghijklmnop', str_repeat('1', 16)] as $salah) {
        expect(fn () => new Nik($salah))->toThrow(InvalidPatientData::class);
    }
});

it('PAS-01 NIK ditampilkan tersamar dan nomor HP dinormalkan', function () {
    expect((new Nik('9999010101900001'))->masked())->toBe('************0001');
    foreach (['08123456789', '+62 812-3456-789', '628123456789', '8123456789'] as $input) {
        expect((new PhoneNumber($input))->value)->toBe('628123456789');
    }
    expect((new PhoneNumber('08123456789'))->local())->toBe('08123456789')
        ->and((new PhoneNumber('08123456789'))->masked())->toBe('0812***6789');
    expect(fn () => new PhoneNumber('12345'))->toThrow(InvalidPatientData::class);
    expect(PhoneNumber::tryFrom('bukan nomor'))->toBeNull();
});

it('PAS-01 kunci nama menghapus tanda baca dan membandingkan kemiripan', function () {
    expect(NameKey::of('  Drg. Siti  Nur-Aini '))->toBe('drg siti nur aini');
    expect(NameKey::similar('Budi Santoso', 'budi santosa'))->toBeTrue()
        ->and(NameKey::similar('Budi Santoso', 'Ani Wijaya'))->toBeFalse();
});

it('PAS-01 NIK wajib; bila belum ada, alasan wajib ditulis', function () {
    expect(fn () => draft(['nik' => null])->toProfile(hariIni()))->toThrow(InvalidPatientData::class);

    $profil = draft(['nik' => null, 'nikMissingReason' => 'WNA, paspor menyusul'])->toProfile(hariIni());
    expect($profil->nik)->toBeNull()->and($profil->nikMissingReason)->toBe('WNA, paspor menyusul');

    expect(fn () => draft(['nikMissingReason' => 'ada alasan'])->toProfile(hariIni()))->toThrow(InvalidPatientData::class);
});

it('PAS-01 tanggal lahir di masa depan atau tidak nyata ditolak', function () {
    expect(fn () => draft(['birthDate' => '2026-01-01'])->toProfile(hariIni()))->toThrow(InvalidPatientData::class);
    expect(fn () => draft(['birthDate' => '2020-02-30'])->toProfile(hariIni()))->toThrow(InvalidPatientData::class);
    expect(fn () => draft(['sex' => 'lain'])->toProfile(hariIni()))->toThrow(InvalidPatientData::class);
});

it('PAS-06 pasien di bawah 18 tahun wajib punya wali', function () {
    $anak = ['birthDate' => '2012-06-16']; // 12 tahun kurang sehari dari ulang tahun ke-13 pada HARI_INI
    expect(fn () => draft($anak)->toProfile(hariIni()))->toThrow(InvalidPatientData::class);

    $profil = draft($anak + ['guardianName' => 'Siti Aminah', 'guardianRelation' => 'Ibu'])->toProfile(hariIni());
    expect($profil->guardian?->name)->toBe('Siti Aminah')->and($profil->age(hariIni()))->toBe(12);
});

it('PAS-06 batas 18 tahun tepat: sudah dewasa pada hari ulang tahun', function () {
    expect(draft(['birthDate' => '2007-06-15'])->toProfile(hariIni())->age(hariIni()))->toBe(18);
    expect(fn () => draft(['birthDate' => '2007-06-16'])->toProfile(hariIni()))->toThrow(InvalidPatientData::class);
});

it('PAS-01 tampilan audit menyamarkan NIK dan nomor HP', function () {
    $audit = draft(['phone' => '08123456789'])->toProfile(hariIni())->auditView();

    expect($audit['nik'])->toBe('************0001')->and($audit['phone'])->toBe('0812***6789');
    expect(json_encode($audit))->not->toContain('9999010101900001')->not->toContain('08123456789');
});

it('PAS-04 alergi dan penyakit sistemik adalah peringatan; obat rutin bukan', function () {
    expect((new MedicalHistoryEntry(MedicalHistoryType::Allergy, 'Penisilin'))->isWarning())->toBeTrue()
        ->and((new MedicalHistoryEntry(MedicalHistoryType::SystemicDisease, 'Diabetes'))->isWarning())->toBeTrue()
        ->and((new MedicalHistoryEntry(MedicalHistoryType::RegularMedication, 'Metformin'))->isWarning())->toBeFalse();
    expect(fn () => new MedicalHistoryEntry(MedicalHistoryType::Allergy, '  '))->toThrow(InvalidPatientData::class);
});
