<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Insurance\Presentation\Http\Controllers\ClaimController;
use Src\Insurance\Presentation\Http\Controllers\PolicyController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

$id = '[0-9A-Z]{26}';

Route::middleware('can:invoice.view')->prefix('insurance')->group(function () use ($id): void {
    Route::get('/claims', [ClaimController::class, 'index'])->name('insurance.claims');
    Route::get('/claims/{claim}', [ClaimController::class, 'show'])->name('insurance.claims.show')->where('claim', $id);
});

Route::middleware('can:invoice.edit')->prefix('insurance')->group(function () use ($id): void {
    Route::get('/claims/create', [ClaimController::class, 'create'])->name('insurance.claims.create');
    Route::post('/claims', [ClaimController::class, 'store']);
    Route::post('/claims/{claim}/submit', [ClaimController::class, 'submit'])->where('claim', $id);
    Route::post('/claims/{claim}/cancel', [ClaimController::class, 'cancel'])->where('claim', $id);
    Route::post('/claims/{claim}/approve', [ClaimController::class, 'approve'])->where('claim', $id);
    Route::post('/claims/{claim}/reject', [ClaimController::class, 'reject'])->where('claim', $id);
});

Route::middleware('can:payment.create')->prefix('insurance')->group(function () use ($id): void {
    Route::post('/claims/{claim}/receive', [ClaimController::class, 'receive'])->where('claim', $id)->middleware('throttle:30,1');
});

Route::middleware('can:report.financial')->prefix('insurance')->group(function (): void {
    Route::get('/receivables', [ClaimController::class, 'receivables'])->name('insurance.receivables');
});

Route::middleware('can:patient.view')->group(function () use ($id): void {
    Route::get('/patients/{patient}/insurance', [PolicyController::class, 'index'])->name('patients.insurance')->where('patient', $id);
});

Route::middleware('can:patient.update')->group(function () use ($id): void {
    Route::post('/patients/{patient}/insurance', [PolicyController::class, 'store'])->where('patient', $id);
    Route::put('/patients/{patient}/insurance/{policy}', [PolicyController::class, 'update'])->where('patient', $id)->where('policy', $id);
    Route::post('/patients/{patient}/insurance/{policy}/deactivate', [PolicyController::class, 'deactivate'])->where('patient', $id)->where('policy', $id);
    Route::post('/patients/{patient}/insurance/{policy}/activate', [PolicyController::class, 'activate'])->where('patient', $id)->where('policy', $id);
});
