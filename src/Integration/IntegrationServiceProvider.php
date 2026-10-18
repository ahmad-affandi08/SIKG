<?php

declare(strict_types=1);

namespace Src\Integration;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Integration\Application\QueueEncounterSubmission;
use Src\Integration\Application\Reminder\WhatsAppBookingCodeSender;
use Src\Integration\Application\Reminder\WhatsAppGateway;
use Src\Integration\Application\SatuSehatGateway;
use Src\Integration\Domain\IdentifierRepository;
use Src\Integration\Domain\Reminder\ReminderRepository;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\SatuSehatSettingsRepository;
use Src\Integration\Domain\SubmissionRepository;
use Src\Integration\Infrastructure\Console\RunRemindersCommand;
use Src\Integration\Infrastructure\Console\SubmitEncountersCommand;
use Src\Integration\Infrastructure\EloquentIdentifierRepository;
use Src\Integration\Infrastructure\EloquentReminderRepository;
use Src\Integration\Infrastructure\EloquentReminderSettingsRepository;
use Src\Integration\Infrastructure\EloquentSatuSehatSettingsRepository;
use Src\Integration\Infrastructure\EloquentSubmissionRepository;
use Src\Integration\Infrastructure\HttpSatuSehatGateway;
use Src\Integration\Infrastructure\WhatsAppCloudGateway;
use Src\Scheduling\Contracts\BookingCodeSender;

final class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SatuSehatSettingsRepository::class, EloquentSatuSehatSettingsRepository::class);
        $this->app->singleton(IdentifierRepository::class, EloquentIdentifierRepository::class);
        $this->app->singleton(SubmissionRepository::class, EloquentSubmissionRepository::class);
        $this->app->singleton(SatuSehatGateway::class, HttpSatuSehatGateway::class);
        $this->app->singleton(ReminderSettingsRepository::class, EloquentReminderSettingsRepository::class);
        $this->app->singleton(ReminderRepository::class, EloquentReminderRepository::class);
        $this->app->singleton(WhatsAppGateway::class, WhatsAppCloudGateway::class);
        // P3-01: kode verifikasi booking online lewat WhatsApp API klinik (menggantikan bawaan tanpa saluran milik Scheduling).
        $this->app->singleton(BookingCodeSender::class, WhatsAppBookingCodeSender::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SubmitEncountersCommand::class, RunRemindersCommand::class]);
        }

        // P2-01: kunjungan yang ditutup masuk antrean pengiriman SATUSEHAT (dispatch setelah commit).
        Event::listen(EncounterClosed::class, [QueueEncounterSubmission::class, 'handle']);

        Route::middleware(['web', 'auth'])
            ->group(__DIR__.'/Presentation/routes.php');
    }
}
