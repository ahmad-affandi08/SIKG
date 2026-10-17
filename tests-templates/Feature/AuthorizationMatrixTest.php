<?php

// TEMPLAT: salin ke tests/Feature/AuthorizationMatrixTest.php
// Sumber kebenaran: docs/PRD/00-ringkasan-lingkup-persona.md (matriks hak akses). Satu baris dataset per sel.
// Tambahkan baris sampai SEMUA sel matriks tercakup (target 100%).

declare(strict_types=1);

use App\Models\User;

// [peran, metode, uri-factory, status-yang-diharapkan]
dataset('matriks', [
    'resepsionis boleh membuat pasien'        => ['resepsionis', 'POST', '/patients', 302],
    'dokter tidak boleh membuat pasien'       => ['dokter', 'POST', '/patients', 403],
    'kasir tidak boleh membuka rekam medis'   => ['kasir', 'GET', '/encounters/{encounter}', 403],
    'dokter boleh menutup kunjungan sendiri'  => ['dokter', 'POST', '/encounters/{encounter}/close', 302],
    'kasir tidak boleh void tagihan'          => ['kasir', 'POST', '/invoices/{invoice}/void', 403],
    'admin boleh void tagihan'                => ['admin', 'POST', '/invoices/{invoice}/void', 302],
    'pemilik boleh melihat audit log'         => ['pemilik', 'GET', '/settings/audit-logs', 200],
    'resepsionis tidak boleh melihat audit'   => ['resepsionis', 'GET', '/settings/audit-logs', 403],
]);

it('AUTH-02 hak akses sesuai matriks', function (string $peran, string $metode, string $uri, int $status) {
    $user = User::factory()->withRole($peran)->create();   // sesuaikan factory
    $uri = resolveFixtureUri($uri);                         // ganti {encounter}/{invoice} dengan data sintetis
    $response = $this->actingAs($user)->call($metode, $uri, validPayloadFor($metode, $uri));
    expect($response->getStatusCode())->toBe($status);
})->with('matriks');

it('AUTH-02 tanpa login diarahkan ke login', function () {
    $this->get('/patients')->assertRedirect('/login');
});
