<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Batas laju dihitung per rute: memanggil rute berbatas besar (mis. slot booking) tidak boleh menghabiskan jatah rute berbatas kecil
 * (mis. kirim permintaan) milik IP atau pengguna yang sama.
 */
it('SYS-07 batas laju dihitung terpisah per rute untuk IP yang sama', function () {
    Route::middleware(['web', 'throttle:60,1'])->get('/uji-batas/banyak', fn () => 'ok');
    Route::middleware(['web', 'throttle:2,1'])->get('/uji-batas/sedikit', fn () => 'ok');

    foreach (range(1, 10) as $i) {
        $this->get('/uji-batas/banyak')->assertOk();
    }

    $this->get('/uji-batas/sedikit')->assertOk();
    $this->get('/uji-batas/sedikit')->assertOk();
    $this->get('/uji-batas/sedikit')->assertStatus(429);
});

it('SYS-07 batas rute tetap berlaku per IP: IP lain tidak ikut terblokir', function () {
    Route::middleware(['web', 'throttle:1,1'])->get('/uji-batas/satu', fn () => 'ok');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/uji-batas/satu')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get('/uji-batas/satu')->assertStatus(429);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->get('/uji-batas/satu')->assertOk();
});
