<?php

declare(strict_types=1);

namespace Src\Clinical;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;
use Src\Clinical\Application\FileVault;
use Src\Clinical\Application\OpenEncounterListener;
use Src\Clinical\Contracts\EncounterExportLookup;
use Src\Clinical\Contracts\EncounterFileLookup;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Clinical\Contracts\RecallLookup;
use Src\Clinical\Domain\ClinicalFileRepository;
use Src\Clinical\Domain\DocumentRepository;
use Src\Clinical\Domain\DocumentSignatureRepository;
use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\OdontogramRepository;
use Src\Clinical\Domain\TreatmentPlanRepository;
use Src\Clinical\Infrastructure\Eloquent\ClinicalDocumentModel;
use Src\Clinical\Infrastructure\Eloquent\ClinicalFileModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;
use Src\Clinical\Infrastructure\Eloquent\OdontogramSnapshotModel;
use Src\Clinical\Infrastructure\Eloquent\TreatmentPlanModel;
use Src\Clinical\Infrastructure\EloquentClinicalFileRepository;
use Src\Clinical\Infrastructure\EloquentDiagnosisCatalog;
use Src\Clinical\Infrastructure\EloquentDocumentRepository;
use Src\Clinical\Infrastructure\EloquentDocumentSignatureRepository;
use Src\Clinical\Infrastructure\EloquentEncounterExportLookup;
use Src\Clinical\Infrastructure\EloquentEncounterFileLookup;
use Src\Clinical\Infrastructure\EloquentEncounterLookup;
use Src\Clinical\Infrastructure\EloquentEncounterRepository;
use Src\Clinical\Infrastructure\EloquentOdontogramRepository;
use Src\Clinical\Infrastructure\EloquentRecallLookup;
use Src\Clinical\Infrastructure\EloquentTreatmentPlanRepository;
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
        $this->app->tag(['sikg.patient-mover.encounters', 'sikg.patient-mover.odontograms', 'sikg.patient-mover.files', 'sikg.patient-mover.plans', 'sikg.patient-mover.documents'], 'sikg.patient-movers');
        $this->app->singleton(OdontogramRepository::class, EloquentOdontogramRepository::class);
        $this->app->singleton(ClinicalFileRepository::class, EloquentClinicalFileRepository::class);
        $this->app->singleton(FileVault::class, fn ($app) => new PrivateFileVault($app->make(PiiProtector::class), $app->make(IdGenerator::class), $app->make(TenantContext::class), (bool) config('sikg.encrypt_files')));
        $this->app->singleton(EncounterLookup::class, EloquentEncounterLookup::class);
        $this->app->singleton(EncounterExportLookup::class, EloquentEncounterExportLookup::class);
        $this->app->singleton(RecallLookup::class, EloquentRecallLookup::class);
        $this->app->singleton(EncounterFileLookup::class, EloquentEncounterFileLookup::class);
        $this->app->singleton(EncounterRepository::class, EloquentEncounterRepository::class);
        $this->app->singleton(TreatmentPlanRepository::class, EloquentTreatmentPlanRepository::class);
        $this->app->singleton(DocumentRepository::class, EloquentDocumentRepository::class);
        $this->app->singleton(DocumentSignatureRepository::class, EloquentDocumentSignatureRepository::class);
        $this->app->bind('sikg.patient-mover.documents', fn ($app) => new ModelPatientMover('Dokumen klinis', ClinicalDocumentModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.plans', fn ($app) => new ModelPatientMover('Rencana perawatan', TreatmentPlanModel::class, $app->make(TenantContext::class)));
        $this->app->singleton(DiagnosisCatalog::class, EloquentDiagnosisCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // RM-09 dan RM-10: informed consent disiapkan tenaga klinis (encounter.write) dan surat diterbitkan dokter (encounter.close);
        // Admin mencetak lewat record.print. Pemeriksaan per jenis dilakukan di DocumentController.
        Gate::define('documents.consent', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['encounter.write', 'record.print']));
        Gate::define('documents.letter', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['encounter.close', 'record.print']));
        Gate::define('documents.issue', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['documents.consent', 'documents.letter']));

        // RM-01: kunjungan dibuat saat pasien check-in.
        Event::listen(PatientCheckedIn::class, [OpenEncounterListener::class, 'handle']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
