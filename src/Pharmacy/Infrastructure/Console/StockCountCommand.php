<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Pharmacy\Application\StockCountService;
use Src\Shared\Application\TenantContext;

/**
 * P2-05: membuka sesi opname terjadwal tiap klinik yang jadwalnya aktif dan sudah jatuh tempo. Jadwal maju sekali per jatuh
 * tempo, jadi tahan cron yang terlewat maupun dijalankan berulang.
 */
final class StockCountCommand extends Command
{
    protected $signature = 'sikg:stock:count';

    protected $description = 'Buka sesi opname stok terjadwal yang sudah jatuh tempo';

    public function handle(StockCountService $counts, TenantContext $tenant): int
    {
        $opened = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $opened += $tenant->runAs((string) $clinicId, null, fn () => $counts->runDue()) ? 1 : 0;
        }
        $this->info("Sesi opname baru dibuka: {$opened}");

        return self::SUCCESS;
    }
}
