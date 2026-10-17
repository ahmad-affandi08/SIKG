<?php

declare(strict_types=1);

namespace Src\Patient;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinic\Contracts\MedicalRecordNumbering;
use Src\Patient\Application\Import\ImportChunkScheduler;
use Src\Patient\Application\Import\ImportFileStore;
use Src\Patient\Application\Import\ImportRowMapper;
use Src\Patient\Application\Import\ProcessImportChunk;
use Src\Patient\Application\Import\SpreadsheetReader;
use Src\Patient\Application\Import\StartPatientImport;
use Src\Patient\Application\Merge\MergePatients;
use Src\Patient\Contracts\PatientAlerts;
use Src\Patient\Contracts\PatientContactLookup;
use Src\Patient\Contracts\PatientLookup;
use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Patient\Domain\PatientContactRepository;
use Src\Patient\Domain\PatientRepository;
use Src\Patient\Infrastructure\Console\TelegramPollCommand;
use Src\Patient\Infrastructure\EloquentImportBatchRepository;
use Src\Patient\Infrastructure\EloquentPatientAlerts;
use Src\Patient\Infrastructure\EloquentPatientContactLookup;
use Src\Patient\Infrastructure\EloquentPatientContactRepository;
use Src\Patient\Infrastructure\EloquentPatientLookup;
use Src\Patient\Infrastructure\EloquentPatientRepository;
use Src\Patient\Infrastructure\Import\PrivateImportFileStore;
use Src\Patient\Infrastructure\Import\QueueImportChunkScheduler;
use Src\Patient\Infrastructure\Import\XlsxSpreadsheetReader;
use Src\Patient\Infrastructure\PatientContactMover;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

final class PatientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PatientRepository::class, EloquentPatientRepository::class);
        $this->app->singleton(PatientAlerts::class, EloquentPatientAlerts::class);
        $this->app->singleton(PatientLookup::class, EloquentPatientLookup::class);
        $this->app->singleton(PatientContactRepository::class, EloquentPatientContactRepository::class);
        $this->app->singleton(PatientContactLookup::class, EloquentPatientContactLookup::class);
        $this->app->bind('sikg.patient-mover.contacts', fn ($app) => new PatientContactMover($app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.contacts'], 'sikg.patient-movers');
        $this->app->singleton(ImportBatchRepository::class, EloquentImportBatchRepository::class);
        $this->app->singleton(SpreadsheetReader::class, XlsxSpreadsheetReader::class);
        $this->app->singleton(ImportChunkScheduler::class, QueueImportChunkScheduler::class);
        $this->app->singleton(ImportFileStore::class, fn ($app) => new PrivateImportFileStore($app->make(PiiProtector::class), $app->make(IdGenerator::class), (bool) config('sikg.encrypt_files')));
        $this->app->bind(MergePatients::class, fn ($app) => new MergePatients(
            $app->make(PatientRepository::class), $app->tagged('sikg.patient-movers'), $app->make(Transaction::class),
            $app->make(AuditLogger::class), $app->make(AccessLogger::class), $app->make(Clock::class),
        ));
        $this->app->bind(StartPatientImport::class, fn ($app) => new StartPatientImport(
            $app->make(ImportFileStore::class), $app->make(SpreadsheetReader::class), $app->make(ImportRowMapper::class), $app->make(PatientRepository::class),
            $app->make(ImportBatchRepository::class), $app->make(Transaction::class), $app->make(AuditLogger::class), $app->make(Clock::class),
            (int) config('sikg.import_max_rows'), (int) config('sikg.chunk_size'),
        ));
        $this->app->bind(ProcessImportChunk::class, fn ($app) => new ProcessImportChunk(
            $app->make(ImportFileStore::class), $app->make(SpreadsheetReader::class), $app->make(ImportRowMapper::class), $app->make(PatientRepository::class),
            $app->make(ImportBatchRepository::class), $app->make(MedicalRecordNumbering::class), $app->make(ImportChunkScheduler::class),
            $app->make(Transaction::class), $app->make(AuditLogger::class), $app->make(Clock::class), (int) config('sikg.chunk_size'),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([TelegramPollCommand::class]);
        }

        // Riwayat medis (PAS-04) diperbarui perawat saat anamnesis (encounter.write) dan petugas pendaftaran (patient.update).
        Gate::define('patient.history.manage', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['patient.update', 'encounter.write']));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
