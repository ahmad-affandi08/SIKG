<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Integration\Presentation\Http\Controllers\ReminderController;
use Src\Integration\Presentation\Http\Controllers\SatuSehatController;
use Src\Integration\Presentation\Http\Controllers\WhatsAppSettingsController;

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

// P2-03: meja pengingat bagi petugas jadwal; pengaturan hanya untuk pemegang izin setting.manage.
$id = '[0-9A-Z]{26}';

Route::middleware('can:appointment.manage')->prefix('reminders')->group(function () use ($id): void {
    Route::get('/', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('/{reminder}/sent', [ReminderController::class, 'markSent'])->where('reminder', $id);
    Route::post('/{reminder}/skip', [ReminderController::class, 'skip'])->where('reminder', $id);
    Route::post('/{reminder}/opt-out', [ReminderController::class, 'optOut'])->where('reminder', $id);
    Route::post('/patients/{patient}/opt-in', [ReminderController::class, 'optIn'])->where('patient', $id);
});

Route::middleware(['can:appointment.manage', 'throttle:60,1'])->prefix('internal-api/reminders')->group(function () use ($id): void {
    Route::post('/{reminder}/link', [ReminderController::class, 'link'])->where('reminder', $id);
});

Route::middleware('can:setting.manage')->prefix('settings/reminders')->group(function (): void {
    Route::get('/', [ReminderController::class, 'settings'])->name('settings.reminders');
    Route::put('/', [ReminderController::class, 'saveSettings']);
});

// Saluran WhatsApp (WhatsApp Resmi atau penyedia tidak resmi) untuk pengingat, kode booking, dan pemberitahuan staf.
Route::middleware('can:setting.manage')->prefix('settings/whatsapp')->group(function (): void {
    Route::get('/', [WhatsAppSettingsController::class, 'index'])->name('settings.whatsapp');
    Route::put('/', [WhatsAppSettingsController::class, 'update']);
    Route::post('/test', [WhatsAppSettingsController::class, 'test'])->middleware('throttle:10,1');
});
