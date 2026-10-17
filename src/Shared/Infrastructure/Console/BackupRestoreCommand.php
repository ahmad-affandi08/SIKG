<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\Backup\BackupFailed;
use Src\Shared\Application\Backup\BackupService;

/**
 * SYS-02: pemulihan bencana. Hanya ke basis data baru yang sudah dimigrasi sampai versi yang sama dan tanpa data; perintah
 * menolak basis data yang berisi. Tidak pernah menimpa atau menghapus data yang ada (M5, M17).
 */
final class BackupRestoreCommand extends Command
{
    protected $signature = 'sikg:backup:restore {file : Nama berkas cadangan} {--confirm : Konfirmasi bahwa basis data tujuan baru dan kosong}';

    protected $description = 'Pulihkan cadangan ke basis data kosong yang sudah dimigrasi (pemulihan bencana)';

    public function handle(BackupService $backups): int
    {
        if (! $this->option('confirm')) {
            $this->error('Tambahkan --confirm setelah memastikan basis data tujuan baru, sudah dimigrasi, dan kosong.');

            return self::FAILURE;
        }

        try {
            $result = $backups->restore((string) $this->argument('file'));
        } catch (BackupFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Dipulihkan: {$result['tables']} tabel, {$result['rows']} baris.");

        return self::SUCCESS;
    }
}
