<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MasterDataController;
use Illuminate\Support\Facades\Route;
use Src\Shared\Application\Master\MasterEntityRegistry;

// Rute modul didefinisikan di Presentation/routes.php tiap konteks (dimuat ServiceProvider konteks).
Route::redirect('/', '/dashboard');

// SYS-07: dilindungi token (header X-Health-Token), bukan sesi; dibatasi laju.
Route::get('/health', HealthController::class)->middleware('throttle:30,1');

// Master data generik: satu set route per entitas terdaftar, izin diambil dari entitas itu (M9).
Route::middleware('auth')->prefix('settings/master')->group(function (): void {
    foreach (app(MasterEntityRegistry::class)->all() as $master) {
        $key = $master->key();

        Route::middleware('can:'.$master->viewPermission())->group(function () use ($key): void {
            Route::get('/'.$key, [MasterDataController::class, 'index'])->defaults('entity', $key)->name('master.'.$key);
        });

        Route::middleware('can:'.$master->managePermission())->group(function () use ($key): void {
            Route::get('/'.$key.'/create', [MasterDataController::class, 'form'])->defaults('entity', $key)->name('master.'.$key.'.create');
            Route::get('/'.$key.'/{id}/edit', [MasterDataController::class, 'form'])->defaults('entity', $key)->name('master.'.$key.'.edit');
            Route::post('/'.$key, [MasterDataController::class, 'store'])->defaults('entity', $key);
            Route::put('/'.$key.'/{id}', [MasterDataController::class, 'update'])->defaults('entity', $key);
            Route::post('/'.$key.'/{id}/activate', [MasterDataController::class, 'activate'])->defaults('entity', $key);
            Route::post('/'.$key.'/{id}/deactivate', [MasterDataController::class, 'deactivate'])->defaults('entity', $key);
        });
    }
});
