<?php

declare(strict_types=1);

namespace Src\Scheduling;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Application\AppointmentWorkflow;
use Src\Scheduling\Contracts\AppointmentLookup;
use Src\Scheduling\Contracts\AppointmentProgress;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Infrastructure\EloquentAppointmentLookup;
use Src\Scheduling\Infrastructure\EloquentAppointmentRepository;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppointmentRepository::class, EloquentAppointmentRepository::class);
        $this->app->singleton(AppointmentLookup::class, EloquentAppointmentLookup::class);
        $this->app->bind(AppointmentProgress::class, AppointmentWorkflow::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
