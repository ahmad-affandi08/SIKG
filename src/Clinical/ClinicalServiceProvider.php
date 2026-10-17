<?php

declare(strict_types=1);

namespace Src\Clinical;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;
use Src\Clinical\Application\FileVault;
use Src\Clinical\Application\OpenEncounterListener;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\OdontogramRepository;
use Src\Clinical\Infrastructure\Eloquent\ClinicalFileModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;
use Src\Clinical\Infrastructure\Eloquent\OdontogramSnapshotModel;
use Src\Clinical\Infrastructure\EloquentClinicalFileRepository;
use Src\Clinical\Infrastructure\EloquentDiagnosisCatalog;
use Src\Clinical\Infrastructure\EloquentEncounterLookup;
use Src\Clinical\Infrastructure\EloquentEncounterRepository;
use Src\Clinical\Infrastructure\EloquentOdontogramRepository;
use Src\Clinical\Infrastructure\PrivateFileVault;
use Src\Scheduling\Domain\Events\PatientCheckedIn;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Infrastructure\ModelPatientMover;

final class ClinicalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('sikg.patient-mover.encounters', fn ($app) => new ModelPatientMover('Kunjungan', EncounterModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.odontograms', fn ($app) => new ModelPatientMover('Odontogram', OdontogramSnapshotModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.files', fn ($app) => new ModelPatientMover('Berkas klinis', ClinicalFileModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.encounters', 'sikg.patient-mover.odontograms', 'sikg.patient-mover.files'], 'sikg.patient-movers');
        $this->app->singleton(OdontogramRepository::class, EloquentOdontogramRepository::class);
        $this->app->singleton(ClinicalFileRepository::class, EloquentClinicalFileRepository::class);
        $this->app->singleton(FileVault::class, fn ($app) => new PrivateFileVault($app->make(PiiProtector::class), $app->make(IdGenerator::class), $app->make(TenantContext::class), (bool) config('sikg.encrypt_files')));
        $this->app->singleton(EncounterLookup::class, EloquentEncounterLookup::class);
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
