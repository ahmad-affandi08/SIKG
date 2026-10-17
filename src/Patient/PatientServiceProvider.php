<?php

declare(strict_types=1);

namespace Src\Patient;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Patient\Domain\PatientRepository;
use Src\Patient\Infrastructure\EloquentPatientRepository;

final class PatientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PatientRepository::class, EloquentPatientRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        // Riwayat medis (PAS-04) diperbarui perawat saat anamnesis (encounter.write) dan petugas pendaftaran (patient.update).
        Gate::define('patient.history.manage', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['patient.update', 'encounter.write']));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
