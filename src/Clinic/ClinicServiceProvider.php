<?php

declare(strict_types=1);

namespace Src\Clinic;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinic\Application\Settings\ClinicSettingsService;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Infrastructure\EloquentBranchDirectory;
use Src\Clinic\Infrastructure\EloquentClinicSettingsService;
use Src\Clinic\Infrastructure\Master\ChairEntity;
use Src\Clinic\Infrastructure\Master\PaymentMethodEntity;
use Src\Clinic\Infrastructure\Master\ProcedureEntity;
use Src\Shared\Application\Master\MasterEntityRegistry;

final class ClinicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BranchDirectory::class, EloquentBranchDirectory::class);
        $this->app->singleton(ClinicSettingsService::class, EloquentClinicSettingsService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        $registry = $this->app->make(MasterEntityRegistry::class);
        $registry->register($this->app->make(ChairEntity::class));
        $registry->register($this->app->make(ProcedureEntity::class));
        $registry->register($this->app->make(PaymentMethodEntity::class));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
