<?php

declare(strict_types=1);

namespace Src\Shared\Application;

use Src\Shared\Domain\DomainEvent;

/**
 * Mengirim event domain SETELAH commit transaksi yang sedang berjalan (atau segera bila tidak ada transaksi).
 */
interface EventDispatcher
{
    public function dispatch(DomainEvent ...$events): void;
}
