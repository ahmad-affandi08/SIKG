<?php

declare(strict_types=1);

namespace Src\Clinic;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinic\Application\Doctors\DoctorAdministration;
use Src\Clinic\Application\Profile\ClinicProfileService;
use Src\Clinic\Application\Settings\ClinicSettingsService;
use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinic\Infrastructure\EloquentBranchDirectory;
use Src\Clinic\Infrastructure\EloquentClinicProfileService;
use Src\Clinic\Infrastructure\EloquentClinicSettingsService;
use Src\Clinic\Infrastructure\EloquentDoctorAdministration;
use Src\Clinic\Infrastructure\EloquentProcedureCatalog;
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
        $this->app->singleton(ProcedureCatalog::class, EloquentProcedureCatalog::class);
        $this->app->singleton(ClinicProfileService::class, EloquentClinicProfileService::class);
        $this->app->singleton(DoctorAdministration::class, EloquentDoctorAdministration::class);
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
