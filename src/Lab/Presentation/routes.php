<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Lab\Presentation\Http\Controllers\LabOrderController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

$id = '[0-9A-Z]{26}';

Route::middleware('can:lab.view')->prefix('lab-orders')->group(function () use ($id): void {
    Route::get('/', [LabOrderController::class, 'index'])->name('lab-orders.index');
    Route::get('/{order}', [LabOrderController::class, 'show'])->name('lab-orders.show')->where('order', $id);
});

Route::middleware('can:encounter.write')->prefix('lab-orders')->group(function () use ($id): void {
    Route::get('/create', [LabOrderController::class, 'create'])->name('lab-orders.create');
    Route::post('/', [LabOrderController::class, 'store']);
    Route::post('/{order}/send', [LabOrderController::class, 'send'])->where('order', $id);
    Route::post('/{order}/receive', [LabOrderController::class, 'receive'])->where('order', $id);
    Route::post('/{order}/cancel', [LabOrderController::class, 'cancel'])->where('order', $id);
});

Route::middleware('can:invoice.edit')->prefix('lab-orders')->group(function () use ($id): void {
    Route::post('/{order}/charge', [LabOrderController::class, 'charge'])->where('order', $id);
});
