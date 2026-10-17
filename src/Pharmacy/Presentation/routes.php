<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Pharmacy\Presentation\Http\Controllers\PrescriptionController;
use Src\Pharmacy\Presentation\Http\Controllers\ProcedureMaterialController;
use Src\Pharmacy\Presentation\Http\Controllers\PurchaseOrderController;
use Src\Pharmacy\Presentation\Http\Controllers\StockController;
use Src\Pharmacy\Presentation\Http\Controllers\StockCountController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

$id = '[0-9A-Z]{26}';

Route::middleware('can:stock.view')->group(function (): void {
    Route::get('/stock', [StockController::class, 'index'])->name('stock');
    Route::get('/stock/{medicine}/history', [StockController::class, 'history'])->name('stock.history')->where('medicine', '[0-9A-Z]{26}');
});

Route::middleware('can:stock.view')->prefix('stock')->group(function () use ($id): void {
    Route::get('/batches', [PurchaseOrderController::class, 'batches'])->name('stock.batches');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('stock.purchase-orders');
    Route::get('/purchase-orders/{order}', [PurchaseOrderController::class, 'show'])->name('stock.purchase-orders.show')->where('order', $id);
    Route::get('/counts', [StockCountController::class, 'index'])->name('stock.counts');
    Route::get('/counts/{session}', [StockCountController::class, 'show'])->name('stock.counts.show')->where('session', $id);
});

Route::middleware('can:stock.manage')->group(function () use ($id): void {
    Route::get('/stock/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('stock.purchase-orders.create');
    Route::post('/stock/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::post('/stock/purchase-orders/{order}/order', [PurchaseOrderController::class, 'order'])->where('order', $id);
    Route::post('/stock/purchase-orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->where('order', $id);
    Route::post('/stock/purchase-orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->where('order', $id);

    Route::post('/stock/counts', [StockCountController::class, 'start']);
    Route::post('/stock/counts/schedule', [StockCountController::class, 'schedule']);
    Route::post('/stock/counts/{session}/save', [StockCountController::class, 'save'])->where('session', $id);
    Route::post('/stock/counts/{session}/complete', [StockCountController::class, 'complete'])->where('session', $id);
    Route::post('/stock/counts/{session}/cancel', [StockCountController::class, 'cancel'])->where('session', $id);

    Route::get('/settings/procedure-materials', [ProcedureMaterialController::class, 'index'])->name('settings.procedure-materials');
    Route::post('/settings/procedure-materials', [ProcedureMaterialController::class, 'store']);
    Route::post('/settings/procedure-materials/{material}/remove', [ProcedureMaterialController::class, 'remove'])->where('material', $id);
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
    Route::get('/encounters/{encounter}/prescriptions/create', [PrescriptionController::class, 'create'])->name('prescriptions.create')->where('encounter', $id);
    Route::post('/encounters/{encounter}/prescriptions', [PrescriptionController::class, 'store'])->where('encounter', $id);
});

Route::middleware('can:prescription.update')->group(function () use ($id): void {
    Route::post('/prescriptions/{prescription}/void', [PrescriptionController::class, 'void'])->where('prescription', $id);
});

Route::middleware('can:prescription.view')->group(function () use ($id): void {
    Route::get('/prescriptions/{prescription}/print', [PrescriptionController::class, 'print'])->name('prescriptions.print')->where('prescription', $id);
});
