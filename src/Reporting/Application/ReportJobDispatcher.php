<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

/**
 * Menaruh pembuatan berkas ke antrean. Pada shared hosting antrean dijalankan cron, jadi berkas dapat menunggu satu
 * interval cron; layar menampilkan status jujur (hosting.md butir 13).
 */
interface ReportJobDispatcher
{
    public function dispatch(string $jobId): void;
}
