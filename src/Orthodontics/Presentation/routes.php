<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Orthodontics\Presentation\Http\Controllers\OrthodonticController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

$id = '[0-9A-Z]{26}';

Route::middleware(['profile.dental', 'can:ortho.view'])->prefix('orthodontics')->group(function () use ($id): void {
    Route::get('/', [OrthodonticController::class, 'index'])->name('orthodontics.index');
    Route::get('/cases/{case}', [OrthodonticController::class, 'show'])->name('orthodontics.show')->where('case', $id);
});

Route::middleware(['profile.dental', 'can:treatment_plan.manage'])->prefix('orthodontics')->group(function () use ($id): void {
    Route::get('/create', [OrthodonticController::class, 'create'])->name('orthodontics.create');
    Route::post('/', [OrthodonticController::class, 'store']);
    Route::post('/cases/{case}/complete', [OrthodonticController::class, 'complete'])->where('case', $id);
    Route::post('/cases/{case}/stop', [OrthodonticController::class, 'stop'])->where('case', $id);
});

Route::middleware(['profile.dental', 'can:encounter.write'])->prefix('orthodontics')->group(function () use ($id): void {
    Route::post('/cases/{case}/controls', [OrthodonticController::class, 'addControl'])->where('case', $id);
});

Route::middleware(['profile.dental', 'can:invoice.edit'])->prefix('orthodontics')->group(function () use ($id): void {
    Route::post('/payment-plan/{item}/charge', [OrthodonticController::class, 'charge'])->where('item', $id);
});
