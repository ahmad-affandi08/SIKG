<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\Backup\BackupService;

/**
 * SYS-02: uji pemulihan terjadwal. Memulihkan cadangan ke tabel sementara dan membandingkan jumlah baris dan checksum.
 */
final class BackupVerifyCommand extends Command
{
    protected $signature = 'sikg:backup:verify {file? : Nama berkas cadangan; bawaan yang terbaru}';

    protected $description = 'Uji pemulihan: pulihkan cadangan ke tabel sementara dan bandingkan dengan manifest';

    public function handle(BackupService $backups): int
    {
        $file = $this->argument('file');
        $result = $backups->verify(is_string($file) && $file !== '' ? $file : null);
        $result['status'] === 'ok' ? $this->info($result['message']) : $this->error($result['message']);

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
