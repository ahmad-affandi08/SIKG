<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Presentation\Http\Controllers\AppointmentController;
use Src\Scheduling\Presentation\Http\Controllers\BookingDeskController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

Route::middleware('can:appointment.view')->group(function (): void {
    Route::get('/schedule', [AppointmentController::class, 'schedule'])->name('schedule');
    Route::get('/queue', [AppointmentController::class, 'queue'])->name('queue');
});

Route::middleware(['can:appointment.view', 'throttle:60,1'])->prefix('internal-api')->group(function (): void {
    Route::get('/queue', [AppointmentController::class, 'queueData']);
    Route::get('/appointments/availability', [AppointmentController::class, 'availability']);
});

Route::middleware('can:appointment.manage')->group(function (): void {
    $id = '[0-9A-Z]{26}';
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit')->where('appointment', $id);
    Route::get('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancelForm'])->name('appointments.cancel')->where('appointment', $id);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->where('appointment', $id);
    Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm'])->where('appointment', $id);
    Route::post('/appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn'])->where('appointment', $id);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->where('appointment', $id);
    Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'noShow'])->where('appointment', $id);
    Route::get('/queue/walk-in', [AppointmentController::class, 'walkInForm'])->name('queue.walk-in');
    Route::post('/walk-ins', [AppointmentController::class, 'walkIn']);
});

// P3-01: meja permintaan booking online dan pengaturannya.
Route::middleware('can:appointment.manage')->group(function (): void {
    $id = '[0-9A-Z]{26}';
    Route::get('/booking-requests', [BookingDeskController::class, 'index'])->name('booking-requests');
    Route::post('/booking-requests/{id}/confirm', [BookingDeskController::class, 'confirm'])->where('id', $id);
    Route::post('/booking-requests/{id}/reject', [BookingDeskController::class, 'reject'])->where('id', $id);
    Route::post('/internal-api/booking-requests/{id}/phone', [BookingDeskController::class, 'phone'])->middleware('throttle:30,1')->where('id', $id);
});

Route::middleware('can:setting.manage')->group(function (): void {
    Route::get('/settings/booking', [BookingDeskController::class, 'settings'])->name('settings.booking');
    Route::post('/settings/booking', [BookingDeskController::class, 'saveSettings']);
});
