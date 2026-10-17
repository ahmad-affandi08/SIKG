<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

/**
 * Menjadwalkan potongan impor berikutnya lewat antrean (driver database, digerakkan cron pada shared hosting).
 */
interface ImportChunkScheduler
{
    public function scheduleNext(string $batchId): void;
}
