<?php

declare(strict_types=1);

namespace Src\Clinical;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;
use Src\Clinical\Application\OpenEncounterListener;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\OdontogramRepository;
use Src\Clinical\Infrastructure\EloquentDiagnosisCatalog;
use Src\Clinical\Infrastructure\EloquentEncounterRepository;
use Src\Clinical\Infrastructure\EloquentOdontogramRepository;
use Src\Scheduling\Domain\Events\PatientCheckedIn;

final class ClinicalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OdontogramRepository::class, EloquentOdontogramRepository::class);
        $this->app->singleton(EncounterRepository::class, EloquentEncounterRepository::class);
        $this->app->singleton(DiagnosisCatalog::class, EloquentDiagnosisCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // RM-01: kunjungan dibuat saat pasien check-in.
        Event::listen(PatientCheckedIn::class, [OpenEncounterListener::class, 'handle']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
