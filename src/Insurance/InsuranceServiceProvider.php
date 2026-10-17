<?php

declare(strict_types=1);

namespace Src\Insurance;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Insurance\Domain\ClaimRepository;
use Src\Insurance\Domain\InsurerDirectory;
use Src\Insurance\Domain\PolicyRepository;
use Src\Insurance\Infrastructure\EloquentClaimRepository;
use Src\Insurance\Infrastructure\EloquentInsurerDirectory;
use Src\Insurance\Infrastructure\EloquentPolicyRepository;
use Src\Insurance\Infrastructure\Master\InsurerEntity;
use Src\Shared\Application\Master\MasterEntityRegistry;

final class InsuranceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InsurerDirectory::class, EloquentInsurerDirectory::class);
        $this->app->singleton(PolicyRepository::class, EloquentPolicyRepository::class);
        $this->app->singleton(ClaimRepository::class, EloquentClaimRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // P2-02: master penjamin memakai halaman master data bersama (/settings/master/insurers).
        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(InsurerEntity::class));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
