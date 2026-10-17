<?php

declare(strict_types=1);

namespace Src\Clinical;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;
use Src\Clinical\Infrastructure\EloquentDiagnosisCatalog;

final class ClinicalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DiagnosisCatalog::class, EloquentDiagnosisCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
