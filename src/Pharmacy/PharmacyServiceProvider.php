<?php

declare(strict_types=1);

namespace Src\Pharmacy;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinical\Domain\Events\ProcedurePerformed;
use Src\Clinical\Domain\Events\ProcedureRetracted;
use Src\Pharmacy\Application\ProcedureMaterialService;
use Src\Pharmacy\Application\StockService;
use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Contracts\PrescriptionLookup;
use Src\Pharmacy\Contracts\StockLedger;
use Src\Pharmacy\Domain\PrescriptionRepository;
use Src\Pharmacy\Domain\ProcedureMaterialRepository;
use Src\Pharmacy\Domain\PurchaseOrderRepository;
use Src\Pharmacy\Domain\StockCountRepository;
use Src\Pharmacy\Domain\StockRepository;
use Src\Pharmacy\Domain\SupplierDirectory;
use Src\Pharmacy\Infrastructure\Console\StockAlertsCommand;
use Src\Pharmacy\Infrastructure\Console\StockCountCommand;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionModel;
use Src\Pharmacy\Infrastructure\EloquentMedicineCatalog;
use Src\Pharmacy\Infrastructure\EloquentPrescriptionLookup;
use Src\Pharmacy\Infrastructure\EloquentPrescriptionRepository;
use Src\Pharmacy\Infrastructure\EloquentProcedureMaterialRepository;
use Src\Pharmacy\Infrastructure\EloquentPurchaseOrderRepository;
use Src\Pharmacy\Infrastructure\EloquentStockCountRepository;
use Src\Pharmacy\Infrastructure\EloquentStockRepository;
use Src\Pharmacy\Infrastructure\EloquentSupplierDirectory;
use Src\Pharmacy\Infrastructure\Master\MedicineEntity;
use Src\Pharmacy\Infrastructure\Master\StockOpeningEntity;
use Src\Pharmacy\Infrastructure\Master\SupplierEntity;
use Src\Pharmacy\Infrastructure\Seeders\MedicineSeeder;
use Src\Pharmacy\Infrastructure\Setup\PharmacySetupSteps;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\Setup\SetupRegistry;
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
        $this->app->singleton(SupplierDirectory::class, EloquentSupplierDirectory::class);
        $this->app->singleton(PurchaseOrderRepository::class, EloquentPurchaseOrderRepository::class);
        $this->app->singleton(ProcedureMaterialRepository::class, EloquentProcedureMaterialRepository::class);
        $this->app->singleton(StockCountRepository::class, EloquentStockCountRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([StockAlertsCommand::class, StockCountCommand::class]);
        }

        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(MedicineEntity::class));
        $this->app->make(MasterEntityRegistry::class)->registerImportOnly($this->app->make(StockOpeningEntity::class));
        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(SupplierEntity::class));

        // MST-10: langkah dan data awal yang dimiliki konteks ini untuk halaman Penyiapan awal.
        $setup = $this->app->make(SetupRegistry::class);
        $setup->registerSteps(new PharmacySetupSteps);
        $setup->registerStarter(new MedicineSeeder);

        // P2-05: bahan habis pakai mengikuti pencatatan dan pembatalan tindakan pada kunjungan.
        Event::listen(ProcedurePerformed::class, [ProcedureMaterialService::class, 'procedurePerformed']);
        Event::listen(ProcedureRetracted::class, [ProcedureMaterialService::class, 'procedureRetracted']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
