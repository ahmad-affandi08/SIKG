<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Presentation\Http\Controllers\PublicBookingController;

// P3-01: halaman booking publik, TANPA login (satu-satunya selain /login). Klinik ditentukan dari {slug} oleh middleware
// ResolveBookingClinic (404 bila booking mati). Semua dibatasi throttle; daftar publik di tests/Feature/RouteAuthorizationCoverageTest.php.
$slug = '[a-z0-9][a-z0-9-]{1,58}[a-z0-9]';
$token = '[a-f0-9]{40}';

Route::get('/book/{slug}', [PublicBookingController::class, 'index'])->middleware('throttle:60,1')->where('slug', $slug);
Route::post('/book/{slug}', [PublicBookingController::class, 'submit'])->middleware('throttle:6,1')->where('slug', $slug);
Route::get('/internal-api/book/{slug}/slots', [PublicBookingController::class, 'slots'])->middleware('throttle:60,1')->where('slug', $slug);
Route::get('/book/{slug}/r/{token}', [PublicBookingController::class, 'status'])->middleware('throttle:60,1')->where('slug', $slug)->where('token', $token);
Route::post('/book/{slug}/r/{token}/verify', [PublicBookingController::class, 'verify'])->middleware('throttle:10,1')->where('slug', $slug)->where('token', $token);
Route::post('/book/{slug}/r/{token}/resend', [PublicBookingController::class, 'resend'])->middleware('throttle:3,1')->where('slug', $slug)->where('token', $token);
Route::post('/book/{slug}/r/{token}/cancel', [PublicBookingController::class, 'cancel'])->middleware('throttle:10,1')->where('slug', $slug)->where('token', $token);
