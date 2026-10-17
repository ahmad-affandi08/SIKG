<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Reporting\Application\ReportExportService;
use Src\Shared\Application\TenantContext;

/**
 * Melepas berkas ekspor laporan yang sudah lewat masa simpan (7 hari). Catatan `report_jobs` tetap ada; hanya berkas di
 * disk privat yang dihapus. Aman dijalankan berulang dan tahan cron yang terlewat.
 */
final class PruneReportFilesCommand extends Command
{
    protected $signature = 'sikg:reports:prune';

    protected $description = 'Hapus berkas ekspor laporan yang sudah kedaluwarsa';

    public function handle(ReportExportService $exports, TenantContext $tenant): int
    {
        $released = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $released += $tenant->runAs((string) $clinicId, null, fn () => $exports->pruneExpired());
        }
        $this->info("Berkas laporan kedaluwarsa dilepas: {$released}");

        return self::SUCCESS;
    }
}
