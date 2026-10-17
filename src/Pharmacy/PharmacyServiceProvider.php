<?php

declare(strict_types=1);

namespace Src\Pharmacy;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Pharmacy\Infrastructure\Master\MedicineEntity;
use Src\Shared\Application\Master\MasterEntityRegistry;

final class PharmacyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ikat interface Domain ke implementasi Infrastructure di sini.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        $this->app->make(MasterEntityRegistry::class)->register($this->app->make(MedicineEntity::class));

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
