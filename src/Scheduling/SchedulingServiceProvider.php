<?php

declare(strict_types=1);

namespace Src\Scheduling;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Scheduling\Application\AppointmentWorkflow;
use Src\Scheduling\Application\NotifyAppointmentBooked;
use Src\Scheduling\Contracts\AppointmentLookup;
use Src\Scheduling\Contracts\AppointmentProgress;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\Events\AppointmentBooked;
use Src\Scheduling\Infrastructure\Console\AppointmentDigestCommand;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Scheduling\Infrastructure\EloquentAppointmentLookup;
use Src\Scheduling\Infrastructure\EloquentAppointmentRepository;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\ModelPatientMover;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('sikg.patient-mover.appointments', fn ($app) => new ModelPatientMover('Janji temu', AppointmentModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.appointments'], 'sikg.patient-movers');
        $this->app->singleton(AppointmentRepository::class, EloquentAppointmentRepository::class);
        $this->app->singleton(AppointmentLookup::class, EloquentAppointmentLookup::class);
        $this->app->bind(AppointmentProgress::class, AppointmentWorkflow::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([AppointmentDigestCommand::class]);
        }

        // SYS-05: janji baru menjadi pemberitahuan staf (dispatch setelah commit).
        Event::listen(AppointmentBooked::class, [NotifyAppointmentBooked::class, 'handle']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
