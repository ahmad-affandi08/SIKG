<?php

declare(strict_types=1);

namespace Src\IdentityAccess;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\IdentityAccess\Application\Users\UserAdministration;
use Src\IdentityAccess\Domain\UserDirectory;
use Src\IdentityAccess\Infrastructure\Auth\EloquentUserDirectory;
use Src\IdentityAccess\Infrastructure\Auth\SikgUserProvider;
use Src\IdentityAccess\Infrastructure\Auth\UserPolicy;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\EloquentUserAdministration;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserDirectory::class, EloquentUserDirectory::class);
        $this->app->singleton(UserAdministration::class, EloquentUserAdministration::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Auth::provider('sikg', SikgUserProvider::make(...));

        Gate::policy(User::class, UserPolicy::class);

        // Rute login berada di grup tamu; rute lain di balik auth (didefinisikan di berkas rute).
        Route::middleware(['web'])->group(__DIR__.'/Presentation/routes.php');
    }
}
