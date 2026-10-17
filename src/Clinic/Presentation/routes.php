<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Clinic\Presentation\Http\Controllers\ClinicSettingsController;
use Src\Clinic\Presentation\Http\Controllers\SystemController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

Route::middleware('can:setting.manage')->prefix('settings')->group(function (): void {
    Route::get('/system', SystemController::class)->name('settings.system');
    Route::get('/clinic', [ClinicSettingsController::class, 'show'])->name('settings.clinic');
    Route::put('/clinic', [ClinicSettingsController::class, 'update']);
    Route::post('/holidays', [ClinicSettingsController::class, 'storeHoliday']);
    Route::delete('/holidays/{holiday}', [ClinicSettingsController::class, 'destroyHoliday']);
});
