<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Clinic\Presentation\Http\Controllers\ClinicProfileController;
use Src\Clinic\Presentation\Http\Controllers\ClinicSettingsController;
use Src\Clinic\Presentation\Http\Controllers\DoctorController;
use Src\Clinic\Presentation\Http\Controllers\ProcedureSearchController;
use Src\Clinic\Presentation\Http\Controllers\ServiceProfileController;
use Src\Clinic\Presentation\Http\Controllers\SystemController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

Route::middleware('can:setting.manage')->prefix('settings')->group(function (): void {
    Route::get('/system', SystemController::class)->name('settings.system');
    Route::get('/clinic', [ClinicSettingsController::class, 'show'])->name('settings.clinic');
    Route::put('/clinic', [ClinicSettingsController::class, 'update']);
    Route::get('/holidays/create', [ClinicSettingsController::class, 'holidayForm'])->name('settings.holidays.create');
    Route::post('/holidays', [ClinicSettingsController::class, 'storeHoliday']);
    Route::delete('/holidays/{holiday}', [ClinicSettingsController::class, 'destroyHoliday']);

    Route::get('/service-profile', [ServiceProfileController::class, 'show'])->name('settings.service-profile');
    Route::put('/service-profile', [ServiceProfileController::class, 'update']);

    Route::get('/profile', [ClinicProfileController::class, 'show'])->name('settings.profile');
    Route::put('/profile', [ClinicProfileController::class, 'update']);
    Route::post('/profile/logo', [ClinicProfileController::class, 'uploadLogo']);
    Route::delete('/profile/logo', [ClinicProfileController::class, 'removeLogo']);
    Route::get('/profile/logo', [ClinicProfileController::class, 'logo'])->name('settings.profile.logo');

    Route::get('/doctors', [DoctorController::class, 'index'])->name('settings.doctors');
    Route::get('/doctors/create', [DoctorController::class, 'create']);
    Route::post('/doctors', [DoctorController::class, 'store']);
    Route::get('/doctors/{doctor}/edit', [DoctorController::class, 'edit']);
    Route::put('/doctors/{doctor}', [DoctorController::class, 'update']);
    Route::post('/doctors/{doctor}/activate', [DoctorController::class, 'activate']);
    Route::post('/doctors/{doctor}/deactivate', [DoctorController::class, 'deactivate']);
});

Route::middleware(['can:tariff.view', 'throttle:60,1'])->prefix('internal-api')->group(function (): void {
    Route::get('/procedures/search', ProcedureSearchController::class);
});
