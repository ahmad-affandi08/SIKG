<?php

declare(strict_types=1);

namespace Src\Orthodontics;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Orthodontics\Domain\OrthoRepository;
use Src\Orthodontics\Infrastructure\Eloquent\OrthoCaseModel;
use Src\Orthodontics\Infrastructure\EloquentOrthoRepository;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\ModelPatientMover;

final class OrthodonticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrthoRepository::class, EloquentOrthoRepository::class);
        // Penggabungan pasien (PAS-07): paket ortodonti pasien ganda berpindah ke pasien utama.
        $this->app->bind('sikg.patient-mover.orthodontics', fn ($app) => new ModelPatientMover('Paket ortodonti', OrthoCaseModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.orthodontics'], 'sikg.patient-movers');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // Rekam medis dan kasir sama-sama perlu melihat paket; catatan klinisnya disaring di use case menurut izin encounter.view.
        Gate::define('ortho.view', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['encounter.view', 'invoice.view']));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
