<?php

declare(strict_types=1);

namespace Src\Lab;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Lab\Domain\LaboratoryDirectory;
use Src\Lab\Domain\LabOrderRepository;
use Src\Lab\Infrastructure\Eloquent\LabOrderModel;
use Src\Lab\Infrastructure\EloquentLaboratoryDirectory;
use Src\Lab\Infrastructure\EloquentLabOrderRepository;
use Src\Lab\Infrastructure\Master\LaboratoryEntity;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\ModelPatientMover;

final class LabServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LabOrderRepository::class, EloquentLabOrderRepository::class);
        $this->app->singleton(LaboratoryDirectory::class, EloquentLaboratoryDirectory::class);
        // Penggabungan pasien (PAS-07): pesanan lab pasien ganda berpindah ke pasien utama.
        $this->app->bind('sikg.patient-mover.lab-orders', fn ($app) => new ModelPatientMover('Pesanan lab', LabOrderModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.lab-orders'], 'sikg.patient-movers');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // Rekam medis dan kasir sama-sama perlu melihat pesanan; uraian klinisnya disaring di use case menurut izin encounter.view.
        Gate::define('lab.view', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['encounter.view', 'invoice.view']));

        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(LaboratoryEntity::class));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
