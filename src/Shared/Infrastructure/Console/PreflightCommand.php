<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\PreflightCheck;

/**
 * SYS-06: `php artisan sikg:preflight`. Keluar dengan kode 1 bila ada butir gagal. Tidak menampilkan rahasia.
 */
final class PreflightCommand extends Command
{
    protected $signature = 'sikg:preflight';

    protected $description = 'Periksa kesiapan lingkungan (PHP, ekstensi, izin tulis, batas, driver, keamanan dasar)';

    public function handle(EnvironmentInspector $inspector): int
    {
        $failed = 0;
        foreach ($inspector->inspect() as $check) {
            $mark = match ($check->status) {
                PreflightCheck::PASS => 'LULUS',
                PreflightCheck::WARN => 'PERINGATAN',
                default => 'GAGAL',
            };
            if ($check->status === PreflightCheck::FAIL) {
                $failed++;
            }
            $this->line(sprintf('[%s] %s: %s', $mark, $check->label, $check->detail));
        }

        $this->newLine();
        $failed === 0 ? $this->info('Lingkungan siap.') : $this->error("{$failed} butir gagal.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
