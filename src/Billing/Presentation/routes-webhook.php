<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Presentation\Http\Controllers\PaymentWebhookController;

// P3-05: webhook penyedia pembayaran. Tanpa grup web (tanpa sesi dan CSRF karena dipanggil server penyedia); keamanannya ada pada token
// acak di alamat, tanda tangan notifikasi, dan pemeriksaan ulang status ke penyedia. Daftar publik: tests/Feature/RouteAuthorizationCoverageTest.php.
Route::post('/webhooks/payments/{token}', [PaymentWebhookController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->where('token', '[a-f0-9]{40}');
