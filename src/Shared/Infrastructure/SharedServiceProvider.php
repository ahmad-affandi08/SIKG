<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\AuditTrailReader;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\Master\MasterEntityRegistry;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Infrastructure\Audit\DatabaseAccessLogger;
use Src\Shared\Infrastructure\Audit\DatabaseAuditLogger;
use Src\Shared\Infrastructure\Audit\EloquentAuditTrailReader;
use Src\Shared\Infrastructure\Console\HealthCheckCommand;
use Src\Shared\Infrastructure\Console\PreflightCommand;
use Src\Shared\Infrastructure\Health\DatabaseHealthMonitor;
use Src\Shared\Infrastructure\Preflight\PhpEnvironmentInspector;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdGenerator::class, UlidIdGenerator::class);
        $this->app->singleton(Transaction::class, DatabaseTransaction::class);
        $this->app->singleton(EventDispatcher::class, AfterCommitEventDispatcher::class);
        $this->app->singleton(SequenceGenerator::class, DatabaseSequenceGenerator::class);
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(ResourceLock::class, DatabaseResourceLock::class);
        $this->app->singleton(MasterEntityRegistry::class);
        $this->app->singleton(PiiProtector::class, fn ($app) => new LaravelPiiProtector($app->make('encrypter'), config('sikg.pii_hash_key')));
        $this->app->singleton(HealthMonitor::class, DatabaseHealthMonitor::class);
        $this->app->singleton(EnvironmentInspector::class, PhpEnvironmentInspector::class);
        $this->app->scoped(AuditLogger::class, DatabaseAuditLogger::class);
        $this->app->scoped(AccessLogger::class, DatabaseAccessLogger::class);
        $this->app->singleton(AuditTrailReader::class, EloquentAuditTrailReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([PreflightCommand::class, HealthCheckCommand::class]);
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
