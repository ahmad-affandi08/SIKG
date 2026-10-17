<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

use Src\Clinical\Presentation\Http\Controllers\DiagnosisSearchController;

Route::middleware(['can:encounter.view', 'throttle:60,1'])->prefix('internal-api')->group(function (): void {
    Route::get('/diagnoses/search', DiagnosisSearchController::class);
});
