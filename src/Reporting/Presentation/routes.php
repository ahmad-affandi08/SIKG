<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Reporting\Presentation\Http\Controllers\ReportController;
use Src\Reporting\Presentation\Http\Controllers\ReportJobController;

// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.
// `reports.open` = report.view atau stock.manage (Perawat melihat laporan stok, LAP-07); laporan dan cakupan yang boleh
// dibuka diputuskan per laporan di Application.

$job = '[0-9A-Z]{26}';
$key = '[a-z-]+';

Route::middleware('can:reports.open')->group(function () use ($job, $key): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{key}', [ReportController::class, 'show'])->middleware('throttle:60,1')->name('reports.show')->where('key', $key);
    Route::get('/report-jobs/{job}/download', [ReportJobController::class, 'download'])->name('report-jobs.download')->where('job', $job);
});

Route::middleware(['can:reports.open', 'throttle:120,1'])->prefix('internal-api')->group(function () use ($job, $key): void {
    Route::post('/reports/{key}/export', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export')->where('key', $key);
    Route::get('/report-jobs/{job}', [ReportJobController::class, 'status'])->name('report-jobs.status')->where('job', $job);
});
