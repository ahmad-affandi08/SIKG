<?php

declare(strict_types=1);

namespace Src\IdentityAccess;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\IdentityAccess\Application\Account\AccountSecurity;
use Src\IdentityAccess\Application\Roles\RoleAdministration;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorSetup;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorStore;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorVerifier;
use Src\IdentityAccess\Application\Users\UserAdministration;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Domain\UserDirectory;
use Src\IdentityAccess\Domain\UserPermissions;
use Src\IdentityAccess\Infrastructure\Auth\EloquentUserDirectory;
use Src\IdentityAccess\Infrastructure\Auth\SikgUserProvider;
use Src\IdentityAccess\Infrastructure\Auth\UserPolicy;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\EloquentAccountSecurity;
use Src\IdentityAccess\Infrastructure\EloquentRoleAdministration;
use Src\IdentityAccess\Infrastructure\EloquentTwoFactorStore;
use Src\IdentityAccess\Infrastructure\EloquentUserAdministration;
use Src\IdentityAccess\Infrastructure\EloquentUserLookup;
use Src\IdentityAccess\Infrastructure\EloquentUserPermissions;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserDirectory::class, EloquentUserDirectory::class);
        $this->app->singleton(UserAdministration::class, EloquentUserAdministration::class);
        $this->app->singleton(UserLookup::class, EloquentUserLookup::class);
        $this->app->singleton(AccountSecurity::class, EloquentAccountSecurity::class);
        $this->app->singleton(UserPermissions::class, EloquentUserPermissions::class);
        $this->app->singleton(RoleAdministration::class, EloquentRoleAdministration::class);
        $this->app->singleton(TwoFactorStore::class, EloquentTwoFactorStore::class);
        $this->app->bind(TwoFactorVerifier::class, fn ($app) => new TwoFactorVerifier($app->make(TwoFactorStore::class), $app->make(Clock::class), (string) config('app.key')));
        $this->app->bind(TwoFactorSetup::class, fn ($app) => new TwoFactorSetup(
            $app->make(TwoFactorStore::class), $app->make(TwoFactorVerifier::class), $app->make(AuditLogger::class),
            $app->make(Transaction::class), $app->make(Clock::class), 'SIKG',
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Auth::provider('sikg', SikgUserProvider::make(...));

        Gate::policy(User::class, UserPolicy::class);
        // AUTH-06: setiap pengguna aktif boleh mengelola kata sandinya sendiri (bukan izin peran).
        Gate::define('account.password', fn (User $user): bool => $user->is_active);
        // AUTH-05: verifikasi dua langkah opsional, hanya untuk Pemilik dan Admin.
        Gate::define('account.two-factor', fn (User $user): bool => $user->is_active && $user->hasAnyRole([Role::Pemilik->value, Role::Admin->value]));
        // SYS-05: setiap pengguna aktif memiliki kotak masuk pemberitahuan; isinya disaring menurut izin.
        Gate::define('notifications.view', fn (User $user): bool => $user->is_active);

        // Rute login berada di grup tamu; rute lain di balik auth (didefinisikan di berkas rute).
        Route::middleware(['web'])->group(__DIR__.'/Presentation/routes.php');
    }
}
