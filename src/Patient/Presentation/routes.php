<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Patient\Presentation\Http\Controllers\PatientController;
use Src\Patient\Presentation\Http\Controllers\PatientImportController;
use Src\Patient\Presentation\Http\Controllers\PatientMergeController;
use Src\Patient\Presentation\Http\Controllers\PatientSearchController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

Route::middleware('can:patient.import')->group(function (): void {
    Route::get('/patients/import', [PatientImportController::class, 'create'])->name('patients.import');
    Route::get('/patients/import/template', [PatientImportController::class, 'template']);
    Route::post('/patients/import', [PatientImportController::class, 'store']);
    Route::get('/patients/import/{batch}', [PatientImportController::class, 'show'])->name('patients.import.show')->where('batch', '[0-9A-Z]{26}');
    Route::post('/patients/import/{batch}/commit', [PatientImportController::class, 'commit'])->where('batch', '[0-9A-Z]{26}');
});

Route::middleware('can:patient.view')->group(function (): void {
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show')->where('patient', '[0-9A-Z]{26}');
});

Route::middleware('can:patient.create')->group(function (): void {
    Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store']);
});

Route::middleware('can:patient.update')->group(function (): void {
    Route::get('/patients/{patient}/edit', [PatientController::class, 'edit'])->where('patient', '[0-9A-Z]{26}');
    Route::put('/patients/{patient}', [PatientController::class, 'update'])->where('patient', '[0-9A-Z]{26}');
    Route::post('/patients/{patient}/consent', [PatientController::class, 'recordConsent'])->where('patient', '[0-9A-Z]{26}');
});

Route::middleware('can:patient.delete')->group(function (): void {
    Route::delete('/patients/{patient}', [PatientController::class, 'archive'])->where('patient', '[0-9A-Z]{26}');
});

Route::middleware('can:patient.merge')->group(function (): void {
    Route::get('/patients/{patient}/merge', [PatientMergeController::class, 'create'])->name('patients.merge')->where('patient', '[0-9A-Z]{26}');
    Route::post('/patients/{patient}/merge', [PatientMergeController::class, 'store'])->where('patient', '[0-9A-Z]{26}');
});

Route::middleware('can:patient.history.manage')->group(function (): void {
    Route::get('/patients/{patient}/history/create', [PatientController::class, 'historyForm'])->name('patients.history.create')->where('patient', '[0-9A-Z]{26}');
    Route::post('/patients/{patient}/history', [PatientController::class, 'addHistory'])->where('patient', '[0-9A-Z]{26}');
    Route::delete('/patients/{patient}/history/{entry}', [PatientController::class, 'removeHistory'])->where('patient', '[0-9A-Z]{26}');
});

Route::middleware(['can:patient.view', 'throttle:60,1'])->prefix('internal-api')->group(function (): void {
    Route::post('/patients/search', [PatientSearchController::class, 'search']);
    Route::post('/patients/duplicate-check', [PatientSearchController::class, 'duplicates']);
});
