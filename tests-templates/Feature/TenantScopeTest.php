<?php

// TEMPLAT: salin ke tests/Feature/TenantScopeTest.php

declare(strict_types=1);

use App\Models\User;

it('AUTH-02 pengguna klinik A tidak dapat membaca pasien klinik B (IDOR)', function () {
    $klinikA = createClinic();            // helper uji, data sintetis
    $klinikB = createClinic();
    $pasienB = createPatient($klinikB);
    $dokterA = User::factory()->withRole('dokter')->forClinic($klinikA)->create();

    $this->actingAs($dokterA)->get("/patients/{$pasienB->id}")->assertNotFound();
    $this->actingAs($dokterA)->putJson("/patients/{$pasienB->id}", ['name' => 'X'])->assertNotFound();
});

it('AUTH-02 pencarian tidak mengembalikan pasien klinik lain', function () {
    $a = createClinic();
    $b = createClinic();
    createPatient($b, ['name' => 'Budi Uji']);
    $user = User::factory()->withRole('resepsionis')->forClinic($a)->create();

    $this->actingAs($user)->getJson('/internal-api/patients/search?q=Budi')
        ->assertOk()->assertJsonCount(0, 'data');
});
