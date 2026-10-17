<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Rute modul didefinisikan di Presentation/routes.php tiap konteks (dimuat ServiceProvider konteks).
Route::redirect('/', '/dashboard');

// SYS-07: dilindungi token (header X-Health-Token), bukan sesi; dibatasi laju.
Route::get('/health', HealthController::class)->middleware('throttle:30,1');
