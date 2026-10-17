<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Infrastructure\Audit\DatabaseAccessLogger;
use Src\Shared\Infrastructure\Audit\DatabaseAuditLogger;

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
        $this->app->scoped(AuditLogger::class, DatabaseAuditLogger::class);
        $this->app->scoped(AccessLogger::class, DatabaseAccessLogger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
