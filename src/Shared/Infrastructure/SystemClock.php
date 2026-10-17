<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Src\Shared\Application\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        // Carbon::now() mengikuti Carbon::setTestNow() sehingga tes dapat memajukan waktu.
        return Carbon::now('UTC')->toDateTimeImmutable();
    }
}
