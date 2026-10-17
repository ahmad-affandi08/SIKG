<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\DomainEvent;

/**
 * Event domain dikirim setelah commit transaksi yang sedang berjalan; bila rollback, event dibuang.
 * Tanpa transaksi aktif, event dikirim segera.
 */
final readonly class AfterCommitEventDispatcher implements EventDispatcher
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            DB::afterCommit(function () use ($event): void {
                $this->events->dispatch($event);
            });
        }
    }
}
