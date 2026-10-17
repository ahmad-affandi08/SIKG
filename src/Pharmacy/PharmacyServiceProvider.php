<?php

declare(strict_types=1);

namespace Src\Pharmacy;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Contracts\PrescriptionLookup;
use Src\Pharmacy\Contracts\StockLedger;
use Src\Pharmacy\Domain\PrescriptionRepository;
use Src\Pharmacy\Domain\StockRepository;
use Src\Pharmacy\Infrastructure\Console\StockAlertsCommand;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionModel;
use Src\Pharmacy\Infrastructure\EloquentMedicineCatalog;
use Src\Pharmacy\Infrastructure\EloquentPrescriptionLookup;
use Src\Pharmacy\Infrastructure\EloquentPrescriptionRepository;
use Src\Pharmacy\Infrastructure\EloquentStockRepository;
use Src\Pharmacy\Infrastructure\Master\MedicineEntity;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\ModelPatientMover;

final class PharmacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('sikg.patient-mover.prescriptions', fn ($app) => new ModelPatientMover('Resep', PrescriptionModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.prescriptions'], 'sikg.patient-movers');
        $this->app->singleton(StockRepository::class, EloquentStockRepository::class);
        $this->app->singleton(PrescriptionRepository::class, EloquentPrescriptionRepository::class);
        $this->app->singleton(PrescriptionLookup::class, EloquentPrescriptionLookup::class);
        $this->app->singleton(MedicineCatalog::class, EloquentMedicineCatalog::class);
        $this->app->singleton(StockLedger::class, StockService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([StockAlertsCommand::class]);
        }

        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(MedicineEntity::class));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
