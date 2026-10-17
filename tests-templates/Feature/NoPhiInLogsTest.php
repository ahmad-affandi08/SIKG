<?php

// TEMPLAT: salin ke tests/Feature/NoPhiInLogsTest.php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

it('NFR-04 galat saat menyimpan pasien tidak mencatat NIK, HP, atau nama ke log', function () {
    Log::spy();
    $nik = '9999000000000001';  // NIK fiktif berawalan 9999
    $hp = '081200000001';

    $this->actingAs(receptionistUser())->post('/patients', [...validPatientPayload(), 'nik' => $nik, 'phone' => $hp, 'name' => 'Pasien Uji Rahasia', 'birth_date' => 'bukan-tanggal']);

    Log::shouldNotHaveReceived('error', fn ($m, $ctx = []) => str_contains(json_encode([$m, $ctx]), $nik) || str_contains(json_encode([$m, $ctx]), $hp) || str_contains(json_encode([$m, $ctx]), 'Pasien Uji Rahasia'));
    expect(file_exists(storage_path('logs/laravel.log')) ? file_get_contents(storage_path('logs/laravel.log')) : '')
        ->not->toContain($nik)->not->toContain($hp);
});

it('PAS-01 NIK dan HP tersimpan terenkripsi dengan kolom hash', function () {
    $this->actingAs(receptionistUser())->post('/patients', [...validPatientPayload(), 'nik' => '9999000000000002'])->assertRedirect();

    $row = DB::table('patients')->latest('created_at')->first();
    expect($row->nik_encrypted)->not->toContain('9999000000000002')
        ->and($row->nik_hash)->not->toBeEmpty();
});
