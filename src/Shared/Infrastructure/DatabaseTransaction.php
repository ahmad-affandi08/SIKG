<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Transaction;

final class DatabaseTransaction implements Transaction
{
    public function run(callable $callback): mixed
    {
        return DB::transaction(fn (): mixed => $callback());
    }
}
