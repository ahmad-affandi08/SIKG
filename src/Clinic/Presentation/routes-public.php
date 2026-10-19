<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Clinic\Presentation\Http\Controllers\LoginBrandLogoController;

// Publik, tanpa login: hanya logo klinik untuk halaman masuk. Daftar publik di tests/Feature/RouteAuthorizationCoverageTest.php.
Route::get('/brand/logo', LoginBrandLogoController::class)->middleware('throttle:60,1')->name('brand.logo');
