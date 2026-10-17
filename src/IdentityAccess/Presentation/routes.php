<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Presentation\Http\Controllers\AuditController;
use Src\IdentityAccess\Presentation\Http\Controllers\DashboardController;
use Src\IdentityAccess\Presentation\Http\Controllers\LoginController;
use Src\IdentityAccess\Presentation\Http\Controllers\UserController;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

// AUTH-03: kelola pengguna. Izin di route; Policy per pengguna sasaran diperiksa di controller.
Route::middleware(['auth', 'can:user.view'])->prefix('settings/users')->group(function (): void {
    Route::get('/', [UserController::class, 'index'])->name('settings.users');
    Route::get('/{user}/edit', [UserController::class, 'edit']);
});

Route::middleware(['auth', 'can:user.manage'])->prefix('settings/users')->group(function (): void {
    Route::get('/create', [UserController::class, 'create']);
    Route::post('/', [UserController::class, 'store']);
    Route::put('/{user}', [UserController::class, 'update']);
    Route::post('/{user}/deactivate', [UserController::class, 'deactivate']);
    Route::post('/{user}/activate', [UserController::class, 'activate']);
    Route::post('/{user}/reset-password', [UserController::class, 'resetPassword']);
});

// SYS-01: audit log dan jejak akses. Hanya Pemilik dan Admin (izin audit.view); semua hanya-baca.
Route::middleware(['auth', 'can:audit.view'])->prefix('audit')->group(function (): void {
    Route::get('/', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/access', [AuditController::class, 'accesses'])->name('audit.access');
    Route::get('/{entry}', [AuditController::class, 'show'])->name('audit.show')->where('entry', '[0-9A-Z]{26}');
});
