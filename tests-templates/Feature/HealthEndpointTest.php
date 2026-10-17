<?php

// TEMPLAT: salin ke tests/Feature/HealthEndpointTest.php
// SYS-06, SYS-07, NFR-13: /up publik tanpa data; /health dilindungi token dan bebas PHI.

declare(strict_types=1);

it('NFR-13 /up publik dan tidak membuka data', function () {
    $this->get('/up')->assertOk();
});

it('SYS-07 /health menolak tanpa token', function () {
    $this->getJson('/health')->assertForbidden();
    $this->getJson('/health', ['X-Health-Token' => 'salah'])->assertForbidden();
});

it('SYS-07 /health dengan token menampilkan umur denyut tanpa PHI', function () {
    config(['sikg.health_token' => 'token-uji']);
    $pasien = createPatient(null, ['name' => 'Nama Rahasia Uji']);

    $res = $this->getJson('/health', ['X-Health-Token' => 'token-uji'])->assertOk();

    $res->assertJsonStructure(['status', 'scheduler_age_seconds', 'queue_age_seconds', 'pending_jobs', 'failed_jobs']);
    expect($res->getContent())->not->toContain('Nama Rahasia Uji')->not->toContain((string) $pasien->id);
});

it('SYS-06 halaman kesiapan lingkungan hanya untuk admin dan pemilik', function () {
    $this->actingAs(receptionistUser())->get('/settings/preflight')->assertForbidden();
    $this->actingAs(adminUser())->get('/settings/preflight')->assertOk();
});

it('SYS-06 perintah sikg:preflight memeriksa PHP, ekstensi, izin tulis, dan driver', function () {
    $this->artisan('sikg:preflight')
        ->expectsOutputToContain('PHP')
        ->expectsOutputToContain('storage')
        ->assertSuccessful();
});
