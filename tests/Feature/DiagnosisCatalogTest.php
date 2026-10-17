<?php

declare(strict_types=1);

use Src\Clinical\Infrastructure\Eloquent\DiagnosisModel;
use Src\Clinical\Infrastructure\Reference\Icd10DentalCatalog;
use Src\IdentityAccess\Domain\Role;

it('MST-07 referensi ICD-10 K00 sampai K14 terisi saat migrasi', function () {
    $kode = DiagnosisModel::query()->pluck('icd10_code')->all();

    expect(count($kode))->toBe(count(Icd10DentalCatalog::all()))
        ->and($kode)->toContain('K02.1', 'K04.0', 'K05.3', 'K08.3', 'K14.9');
    foreach ($kode as $k) {
        expect($k)->toMatch('/^K(0\d|1[0-4])\.\d$/');
    }
});

it('MST-07 pencarian berdasarkan kode mendahulukan kode', function () {
    $dokter = makeUser(Role::Dokter);

    $hasil = $this->actingAs($dokter)->getJson('/internal-api/diagnoses/search?q=K02')
        ->assertOk()->json('data');

    expect($hasil)->not->toBeEmpty()->and($hasil[0]['code'])->toStartWith('K02');
});

it('MST-07 pencarian berdasarkan nama tanpa memedulikan huruf besar', function () {
    $dokter = makeUser(Role::Dokter);

    $hasil = $this->actingAs($dokter)->getJson('/internal-api/diagnoses/search?q=pulpitis')
        ->assertOk()->json('data');

    expect(array_column($hasil, 'code'))->toContain('K04.0');
});

it('MST-07 hasil dibatasi 20 dan hanya kode aktif', function () {
    $dokter = makeUser(Role::Dokter);
    DiagnosisModel::query()->where('icd10_code', 'K04.0')->update(['is_active' => false]);

    $banyak = $this->actingAs($dokter)->getJson('/internal-api/diagnoses/search?q=gigi')->assertOk()->json('data');
    $nonaktif = $this->actingAs($dokter)->getJson('/internal-api/diagnoses/search?q=K04.0')->assertOk()->json('data');

    expect(count($banyak))->toBeLessThanOrEqual(20)->and($nonaktif)->toBe([]);
});

it('MST-07 karakter wildcard dicari apa adanya', function () {
    $dokter = makeUser(Role::Dokter);

    $this->actingAs($dokter)->getJson('/internal-api/diagnoses/search?q=%25')->assertOk()->assertJsonPath('data', []);
});

it('MST-07 pencarian wajib diisi dan butuh izin', function () {
    $this->actingAs(makeUser(Role::Dokter))->getJson('/internal-api/diagnoses/search')->assertStatus(422);
    $this->actingAs(makeUser(Role::Kasir))->getJson('/internal-api/diagnoses/search?q=K02')->assertForbidden();
});

it('MST-07 pencarian tanpa login ditolak', function () {
    $this->getJson('/internal-api/diagnoses/search?q=K02')->assertUnauthorized();
});
