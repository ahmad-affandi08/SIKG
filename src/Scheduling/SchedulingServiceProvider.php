<?php

declare(strict_types=1);

namespace Src\Scheduling;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Ikat interface Domain ke implementasi Infrastructure di sini.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
