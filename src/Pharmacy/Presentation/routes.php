<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Pharmacy\Presentation\Http\Controllers\PrescriptionController;
use Src\Pharmacy\Presentation\Http\Controllers\StockController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

$id = '[0-9A-Z]{26}';

Route::middleware('can:stock.view')->group(function (): void {
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::get('/stock/{medicine}/history', [StockController::class, 'history'])->name('stock.history')->where('medicine', '[0-9A-Z]{26}');
});

Route::middleware(['can:stock.view', 'throttle:60,1'])->prefix('internal-api')->group(function (): void {
    Route::get('/medicines/search', [StockController::class, 'search']);
});

Route::middleware('can:stock.manage')->group(function (): void {
    Route::get('/stock/movements/create', [StockController::class, 'movementForm'])->name('stock.movements.create');
    Route::get('/stock/adjustments/create', [StockController::class, 'adjustmentForm'])->name('stock.adjustments.create');
    Route::post('/stock/movements', [StockController::class, 'movement']);
    Route::post('/stock/adjustments', [StockController::class, 'adjustment']);
});

Route::middleware('can:prescription.create')->group(function () use ($id): void {
    Route::post('/encounters/{encounter}/prescriptions', [PrescriptionController::class, 'store'])->where('encounter', $id);
});

Route::middleware('can:prescription.update')->group(function () use ($id): void {
    Route::post('/prescriptions/{prescription}/void', [PrescriptionController::class, 'void'])->where('prescription', $id);
});

Route::middleware('can:prescription.view')->group(function () use ($id): void {
    Route::get('/prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->name('prescriptions.print')->where('prescription', $id);
});
