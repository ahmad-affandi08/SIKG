<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Queue\Events\Looping;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\AuditTrailReader;
use Src\Shared\Application\Backup\BackupEngine;
use Src\Shared\Application\Backup\BackupRunLog;
use Src\Shared\Application\Backup\BackupService;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\Master\Import\MasterImportStore;
use Src\Shared\Application\Master\Import\MasterRowValidator;
use Src\Shared\Application\Master\Import\MasterSheet;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\Notifications\EmailSender;
use Src\Shared\Application\Notifications\NotificationInbox;
use Src\Shared\Application\Notifications\Notifier;
use Src\Shared\Application\Notifications\TelegramSender;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\Setup\SetupRegistry;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Infrastructure\Audit\DatabaseAccessLogger;
use Src\Shared\Infrastructure\Audit\DatabaseAuditLogger;
use Src\Shared\Infrastructure\Audit\EloquentAuditTrailReader;
use Src\Shared\Infrastructure\Backup\BackupKey;
use Src\Shared\Infrastructure\Backup\EloquentBackupRunLog;
use Src\Shared\Infrastructure\Backup\PhpBackupEngine;
use Src\Shared\Infrastructure\Console\BackupCommand;
use Src\Shared\Infrastructure\Console\BackupPruneCommand;
use Src\Shared\Infrastructure\Console\BackupRestoreCommand;
use Src\Shared\Infrastructure\Console\BackupVerifyCommand;
use Src\Shared\Infrastructure\Console\HealthCheckCommand;
use Src\Shared\Infrastructure\Console\NotificationsPruneCommand;
use Src\Shared\Infrastructure\Console\PreflightCommand;
use Src\Shared\Infrastructure\Health\DatabaseHealthMonitor;
use Src\Shared\Infrastructure\Http\RouteScopedThrottle;
use Src\Shared\Infrastructure\Master\Import\CacheMasterImportStore;
use Src\Shared\Infrastructure\Master\Import\LaravelMasterRowValidator;
use Src\Shared\Infrastructure\Master\Import\PhpSpreadsheetMasterSheet;
use Src\Shared\Infrastructure\Notifications\DatabaseNotificationInbox;
use Src\Shared\Infrastructure\Notifications\DatabaseNotifier;
use Src\Shared\Infrastructure\Notifications\LaravelEmailSender;
use Src\Shared\Infrastructure\Notifications\TelegramBotSender;
use Src\Shared\Infrastructure\Pdf\DompdfRenderer;
use Src\Shared\Infrastructure\Preflight\PhpEnvironmentInspector;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(PdfRenderer::class, DompdfRenderer::class);
        $this->app->singleton(Notifier::class, DatabaseNotifier::class);
        $this->app->singleton(NotificationInbox::class, DatabaseNotificationInbox::class);
        $this->app->singleton(EmailSender::class, LaravelEmailSender::class);
        $this->app->singleton(TelegramSender::class, TelegramBotSender::class);
        $this->app->singleton(IdGenerator::class, UlidIdGenerator::class);
        $this->app->singleton(Transaction::class, DatabaseTransaction::class);
        $this->app->singleton(EventDispatcher::class, AfterCommitEventDispatcher::class);
        $this->app->singleton(SequenceGenerator::class, DatabaseSequenceGenerator::class);
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(ResourceLock::class, DatabaseResourceLock::class);
        $this->app->singleton(MasterEntityRegistry::class);
        $this->app->singleton(SetupRegistry::class);
        // Nama middleware tetap ThrottleRequests (tes dapat mematikannya); isinya menghitung batas per rute.
        $this->app->bind(ThrottleRequests::class, RouteScopedThrottle::class);
        $this->app->singleton(MasterSheet::class, PhpSpreadsheetMasterSheet::class);
        $this->app->singleton(MasterRowValidator::class, LaravelMasterRowValidator::class);
        $this->app->singleton(MasterImportStore::class, CacheMasterImportStore::class);
        $this->app->singleton(PiiProtector::class, fn ($app) => new LaravelPiiProtector($app->make('encrypter'), config('sikg.pii_hash_key')));
        $this->app->singleton(HealthMonitor::class, DatabaseHealthMonitor::class);
        $this->app->singleton(EnvironmentInspector::class, PhpEnvironmentInspector::class);
        $this->app->scoped(AuditLogger::class, DatabaseAuditLogger::class);
        $this->app->scoped(AccessLogger::class, DatabaseAccessLogger::class);
        $this->app->singleton(AuditTrailReader::class, EloquentAuditTrailReader::class);
        $this->app->singleton(BackupRunLog::class, EloquentBackupRunLog::class);
        $this->app->singleton(BackupEngine::class, fn ($app) => new PhpBackupEngine(
            (string) config('sikg.backup_disk'),
            'private',
            BackupKey::resolve(config('sikg.backup_key'), config('app.key')),
        ));
        $this->app->singleton(BackupService::class, fn ($app) => new BackupService(
            $app->make(BackupEngine::class),
            $app->make(BackupRunLog::class),
            $app->make(Clock::class),
            $app->make(AuditLogger::class),
            max(1, (int) config('sikg.backup_interval_hours')),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PreflightCommand::class, HealthCheckCommand::class, BackupCommand::class, BackupVerifyCommand::class, BackupPruneCommand::class, BackupRestoreCommand::class, NotificationsPruneCommand::class]);
        }

        // Denyut antrean: dicatat setiap worker mulai satu putaran, paling sering sekali per 30 detik per proses.
        $lastBeat = 0;
        Event::listen(Looping::class, function () use (&$lastBeat): void {
            if (time() - $lastBeat >= 30) {
                $lastBeat = time();
                $this->app->make(HealthMonitor::class)->beat('queue');
            }
        });
    }
}
