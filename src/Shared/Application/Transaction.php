<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Satu use case, satu transaksi. Event domain yang didispatch di dalamnya dikirim setelah commit.
 */
interface Transaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
