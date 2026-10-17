<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\Backup\BackupService;

/**
 * SYS-02: `php artisan sikg:backup`. Dipanggil scheduler tiap 30 menit; hanya bekerja bila sudah jatuh tempo menurut interval
 * (SIKG_BACKUP_INTERVAL_HOURS). `--force` memaksa cadangan sekarang.
 */
final class BackupCommand extends Command
{
    protected $signature = 'sikg:backup {--force : Buat cadangan sekarang walau belum jatuh tempo}';

    protected $description = 'Cadangkan data (terenkripsi) dan salin berkas unggahan ke disk cadangan';

    public function handle(BackupService $backups): int
    {
        $result = $backups->backup((bool) $this->option('force'));
        $line = $result['message'].($result['file'] === null ? '' : ' ('.$result['file'].')');
        match ($result['status']) {
            'ok' => $this->info($line.($result['files'] === null ? '' : sprintf(' Berkas: %d, disalin %d.', $result['files']['files'], $result['files']['copied']))),
            'skipped' => $this->line($line),
            default => $this->error($line),
        };

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
