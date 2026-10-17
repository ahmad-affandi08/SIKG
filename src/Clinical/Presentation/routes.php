<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
// Semua route di balik 'auth' (dipasang oleh ServiceProvider).
// Setiap route wajib punya pemeriksaan izin: ->middleware('can:<permission>') atau authorize() di controller.

use Src\Clinical\Presentation\Http\Controllers\ClinicalFileController;
use Src\Clinical\Presentation\Http\Controllers\DiagnosisSearchController;
use Src\Clinical\Presentation\Http\Controllers\DocumentController;
use Src\Clinical\Presentation\Http\Controllers\EncounterController;
use Src\Clinical\Presentation\Http\Controllers\OdontogramController;
use Src\Clinical\Presentation\Http\Controllers\OdontogramPrintController;
use Src\Clinical\Presentation\Http\Controllers\TimelineController;
use Src\Clinical\Presentation\Http\Controllers\TreatmentPlanController;

Route::middleware(['can:encounter.view', 'throttle:60,1'])->prefix('internal-api')->group(function (): void {
    Route::get('/diagnoses/search', DiagnosisSearchController::class);
});

$id = '[0-9A-Z]{26}';

Route::middleware('can:encounter.view')->group(function () use ($id): void {
    Route::get('/encounters/{encounter}', [EncounterController::class, 'show'])->name('encounters.show')->where('encounter', $id);
    Route::get('/clinical-files/{file}', [ClinicalFileController::class, 'show'])->name('clinical-files.show')->where('file', $id);
    Route::get('/patients/{patient}/timeline', TimelineController::class)->name('patients.timeline')->where('patient', $id);
    Route::get('/patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'index'])->name('treatment-plans.index')->where('patient', $id);
    Route::get('/patients/{patient}/documents', [DocumentController::class, 'index'])->name('documents.index')->where('patient', $id);
    Route::get('/documents/{document}/print', [DocumentController::class, 'print'])->name('documents.print')->where('document', $id);
});

// Izin per jenis dokumen diperiksa di controller (documents.consent atau documents.letter).
Route::middleware('can:documents.issue')->group(function () use ($id): void {
    Route::get('/patients/{patient}/documents/create', [DocumentController::class, 'create'])->name('documents.create')->where('patient', $id);
    Route::post('/patients/{patient}/documents', [DocumentController::class, 'store'])->where('patient', $id);
});

Route::middleware('can:treatment_plan.manage')->group(function () use ($id): void {
    Route::get('/patients/{patient}/treatment-plans/create', [TreatmentPlanController::class, 'createForm'])->name('treatment-plans.create')->where('patient', $id);
    Route::post('/patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'store'])->where('patient', $id);
    Route::get('/treatment-plans/{plan}/items/create', [TreatmentPlanController::class, 'itemForm'])->name('treatment-plans.items.create')->where('plan', $id);
    Route::post('/treatment-plans/{plan}/items', [TreatmentPlanController::class, 'storeItem'])->where('plan', $id);
    Route::post('/treatment-plans/{plan}/items/{item}/status', [TreatmentPlanController::class, 'changeStatus'])->where('plan', $id)->where('item', $id);
    Route::delete('/treatment-plans/{plan}/items/{item}', [TreatmentPlanController::class, 'removeItem'])->where('plan', $id)->where('item', $id);
    Route::post('/treatment-plans/{plan}/cancel', [TreatmentPlanController::class, 'cancel'])->where('plan', $id);
});

Route::middleware('can:encounter.create')->group(function () use ($id): void {
    Route::post('/encounters', [EncounterController::class, 'store']);
    Route::post('/encounters/{encounter}/start', [EncounterController::class, 'start'])->where('encounter', $id);
});

Route::middleware('can:encounter.write')->group(function () use ($id): void {
    Route::put('/encounters/{encounter}/anamnesis', [EncounterController::class, 'anamnesis'])->where('encounter', $id);
    Route::put('/encounters/{encounter}/notes', [EncounterController::class, 'notes'])->where('encounter', $id);
    Route::get('/encounters/{encounter}/diagnoses/create', [EncounterController::class, 'entryForm'])->defaults('form', 'diagnosis')->where('encounter', $id);
    Route::get('/encounters/{encounter}/procedures/create', [EncounterController::class, 'entryForm'])->defaults('form', 'procedure')->where('encounter', $id);
    Route::get('/encounters/{encounter}/files/create', [EncounterController::class, 'entryForm'])->defaults('form', 'file')->where('encounter', $id);
    Route::post('/encounters/{encounter}/diagnoses', [EncounterController::class, 'addDiagnosis'])->where('encounter', $id);
    Route::delete('/encounters/{encounter}/diagnoses/{entry}', [EncounterController::class, 'removeDiagnosis'])->where('encounter', $id)->where('entry', $id);
    Route::post('/encounters/{encounter}/files', [ClinicalFileController::class, 'store'])->where('encounter', $id);
    Route::delete('/encounters/{encounter}/files/{file}', [ClinicalFileController::class, 'destroy'])->where('encounter', $id)->where('file', $id);
    Route::post('/encounters/{encounter}/procedures', [EncounterController::class, 'addProcedure'])->where('encounter', $id);
    Route::delete('/encounters/{encounter}/procedures/{entry}', [EncounterController::class, 'removeProcedure'])->where('encounter', $id)->where('entry', $id);
});

Route::middleware('can:encounter.close')->group(function () use ($id): void {
    Route::post('/encounters/{encounter}/close', [EncounterController::class, 'close'])->where('encounter', $id);
});

Route::middleware('can:encounter.addendum')->group(function () use ($id): void {
    Route::get('/encounters/{encounter}/addenda/create', [EncounterController::class, 'entryForm'])->defaults('form', 'addendum')->where('encounter', $id);
    Route::post('/encounters/{encounter}/addenda', [EncounterController::class, 'addendum'])->where('encounter', $id);
});

Route::middleware('can:odontogram.view')->group(function () use ($id): void {
    Route::get('/patients/{patient}/odontogram/print', OdontogramPrintController::class)->name('odontogram.print')->where('patient', $id);
});

Route::middleware(['can:odontogram.view', 'throttle:120,1'])->prefix('internal-api')->group(function () use ($id): void {
    Route::get('/patients/{patient}/odontogram', [OdontogramController::class, 'show'])->where('patient', $id);
});

Route::middleware('can:odontogram.write')->group(function () use ($id): void {
    Route::post('/encounters/{encounter}/procedures/{entry}/odontogram', [EncounterController::class, 'resolveOdontogram'])->where('encounter', $id)->where('entry', $id);
});

Route::middleware(['can:odontogram.write', 'throttle:120,1'])->prefix('internal-api')->group(function () use ($id): void {
    Route::put('/encounters/{encounter}/odontogram/teeth/{fdi}', [OdontogramController::class, 'update'])->where('encounter', $id)->where('fdi', '[0-9]{2}');
});
