<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Pharmacy\Application\StockAlerts;
use Src\Shared\Application\TenantContext;

/**
 * OBT-06: menerbitkan peringatan stok harian tiap klinik. Dijadwalkan tiap 30 menit; pemberitahuan terbit sekali per hari
 * sehingga tahan cron yang terlewat maupun terlambat.
 */
final class StockAlertsCommand extends Command
{
    protected $signature = 'sikg:stock:alerts';

    protected $description = 'Terbitkan peringatan stok di bawah minimum dan obat hampir kedaluwarsa';

    public function handle(StockAlerts $alerts, TenantContext $tenant): int
    {
        $raised = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $result = $tenant->runAs((string) $clinicId, null, fn () => $alerts->raiseDaily());
            $raised += (int) $result['low'] + (int) $result['expiring'];
        }
        $this->info("Peringatan stok baru diterbitkan: {$raised}");

        return self::SUCCESS;
    }
}
