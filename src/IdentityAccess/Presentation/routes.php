<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Presentation\Http\Controllers\AccountController;
use Src\IdentityAccess\Presentation\Http\Controllers\AuditController;
use Src\IdentityAccess\Presentation\Http\Controllers\BranchController;
use Src\IdentityAccess\Presentation\Http\Controllers\DashboardController;
use Src\IdentityAccess\Presentation\Http\Controllers\LoginController;
use Src\IdentityAccess\Presentation\Http\Controllers\NotificationController;
use Src\IdentityAccess\Presentation\Http\Controllers\RoleController;
use Src\IdentityAccess\Presentation\Http\Controllers\TwoFactorController;
use Src\IdentityAccess\Presentation\Http\Controllers\TwoFactorLoginController;
use Src\IdentityAccess\Presentation\Http\Controllers\UserController;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    // AUTH-05: langkah kedua masuk; percobaan juga dibatasi di use case.
    Route::get('/login/two-factor', [TwoFactorLoginController::class, 'show'])->name('login.two-factor');
    Route::post('/login/two-factor', [TwoFactorLoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    // P3-04: pilih cabang aktif. Aturan siapa yang boleh ada di use case; hanya mengubah sesi sendiri.
    Route::post('/branch/switch', [BranchController::class, 'switch'])->middleware('throttle:60,1');
});

// AUTH-06: ganti kata sandi sendiri. Gate account.password = pengguna aktif; percobaan dibatasi.
Route::middleware(['auth', 'can:account.password'])->prefix('account')->group(function (): void {
    Route::get('/password', [AccountController::class, 'show'])->name('account.password');
    Route::put('/password', [AccountController::class, 'update'])->middleware('throttle:6,1');
});

// AUTH-05: verifikasi dua langkah opsional untuk Pemilik dan Admin. Gate account.two-factor; percobaan dibatasi.
Route::middleware(['auth', 'can:account.two-factor'])->prefix('account/two-factor')->group(function (): void {
    Route::get('/', [TwoFactorController::class, 'show'])->name('account.two-factor');
    Route::post('/start', [TwoFactorController::class, 'start'])->middleware('throttle:10,1');
    Route::post('/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:6,1');
    Route::post('/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:6,1');
    Route::post('/recovery-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:6,1');
});

// SYS-05: pemberitahuan dalam aplikasi. Gate notifications.view = pengguna aktif; isi disaring menurut izin pengguna.
Route::middleware(['auth', 'can:notifications.view'])->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::middleware('throttle:120,1')->prefix('internal-api/notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'poll']);
        Route::post('/read-all', [NotificationController::class, 'readAll']);
        Route::post('/{notification}/read', [NotificationController::class, 'read'])->where('notification', '[0-9A-Z]{26}');
    });
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
    Route::post('/{user}/reset-two-factor', [UserController::class, 'resetTwoFactor']);
});

// AUTH-07: peran dan izin. Peran Pemilik hanya dapat diubah Pemilik (diperiksa di RoleAdministration).
Route::middleware(['auth', 'can:role.manage'])->prefix('settings/roles')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('settings.roles');
    Route::put('/{role}', [RoleController::class, 'update']);
    Route::post('/{role}/reset', [RoleController::class, 'reset']);
});

// SYS-01: audit log dan jejak akses. Hanya Pemilik dan Admin (izin audit.view); semua hanya-baca.
Route::middleware(['auth', 'can:audit.view'])->prefix('audit')->group(function (): void {
    Route::get('/', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/access', [AuditController::class, 'accesses'])->name('audit.access');
    Route::get('/{entry}', [AuditController::class, 'show'])->name('audit.show')->where('entry', '[0-9A-Z]{26}');
});
