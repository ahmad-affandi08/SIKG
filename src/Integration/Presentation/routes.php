<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Integration\Presentation\Http\Controllers\SatuSehatController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

// P2-01: pengaturan SATUSEHAT hanya untuk pemegang izin setting.manage (Pemilik dan Admin).
Route::middleware('can:setting.manage')->prefix('settings/satusehat')->group(function (): void {
    Route::get('/', [SatuSehatController::class, 'index'])->name('settings.satusehat');
    Route::put('/', [SatuSehatController::class, 'update']);
    Route::post('/test', [SatuSehatController::class, 'test'])->middleware('throttle:10,1');
    Route::put('/doctors/{doctor}', [SatuSehatController::class, 'practitioner'])->where('doctor', '[0-9A-Z]{26}');
    Route::post('/submissions/{submission}/retry', [SatuSehatController::class, 'retry'])->where('submission', '[0-9A-Z]{26}');
});
