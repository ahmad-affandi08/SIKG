<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\Backup\BackupService;

/**
 * SYS-02: membuang cadangan lama sesuai retensi (harian 30 hari, bulanan 12 bulan, tahunan disimpan terus).
 */
final class BackupPruneCommand extends Command
{
    protected $signature = 'sikg:backup:prune';

    protected $description = 'Buang cadangan lama sesuai retensi (harian 30 hari, bulanan 12 bulan, tahunan selamanya)';

    public function handle(BackupService $backups): int
    {
        $result = $backups->prune();
        $result['status'] === 'ok' ? $this->info($result['message']) : $this->error($result['message']);

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
