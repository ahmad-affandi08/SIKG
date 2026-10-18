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
use Src\Scheduling\Contracts\BookingCodeSender;
use Src\Scheduling\Contracts\UpcomingAppointments;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\Booking\BookingRepository;
use Src\Scheduling\Domain\Events\AppointmentBooked;
use Src\Scheduling\Infrastructure\Booking\EloquentBookingRepository;
use Src\Scheduling\Infrastructure\Console\AppointmentDigestCommand;
use Src\Scheduling\Infrastructure\Console\ExpireBookingsCommand;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;
use Src\Scheduling\Infrastructure\Eloquent\BookingRequestModel;
use Src\Scheduling\Infrastructure\EloquentAppointmentLookup;
use Src\Scheduling\Infrastructure\EloquentAppointmentRepository;
use Src\Scheduling\Infrastructure\EloquentUpcomingAppointments;
use Src\Scheduling\Infrastructure\NullBookingCodeSender;
use Src\Scheduling\Presentation\Http\Middleware\ResolveBookingClinic;
use Src\Shared\Application\TenantContext;
use Src\Shared\Infrastructure\ModelPatientMover;

final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('sikg.patient-mover.appointments', fn ($app) => new ModelPatientMover('Janji temu', AppointmentModel::class, $app->make(TenantContext::class)));
        $this->app->bind('sikg.patient-mover.booking', fn ($app) => new ModelPatientMover('Permintaan booking online', BookingRequestModel::class, $app->make(TenantContext::class)));
        $this->app->tag(['sikg.patient-mover.appointments', 'sikg.patient-mover.booking'], 'sikg.patient-movers');
        $this->app->singleton(AppointmentRepository::class, EloquentAppointmentRepository::class);
        $this->app->singleton(AppointmentLookup::class, EloquentAppointmentLookup::class);
        $this->app->singleton(UpcomingAppointments::class, EloquentUpcomingAppointments::class);
        $this->app->bind(AppointmentProgress::class, AppointmentWorkflow::class);
        $this->app->singleton(BookingRepository::class, EloquentBookingRepository::class);
        // Bawaan tanpa saluran kode verifikasi; konteks Integration menggantinya bila WhatsApp API klinik siap (P3-01).
        $this->app->singleton(BookingCodeSender::class, NullBookingCodeSender::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([AppointmentDigestCommand::class, ExpireBookingsCommand::class]);
        }

        // SYS-05: janji baru menjadi pemberitahuan staf (dispatch setelah commit).
        Event::listen(AppointmentBooked::class, [NotifyAppointmentBooked::class, 'handle']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');

        // P3-01: halaman booking publik tanpa login; klinik dari alamat halaman.
        Route::middleware(['web', ResolveBookingClinic::class])
            ->group(__DIR__.'/Presentation/routes-public.php');
    }
}
