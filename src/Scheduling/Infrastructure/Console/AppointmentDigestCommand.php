<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Application\AppointmentDigest;
use Src\Shared\Application\TenantContext;

/**
 * JDW-07: menerbitkan ringkasan janji tiap klinik untuk pemilik dan staf. Dijadwalkan tiap 15 menit; terbit sekali per hari
 * sehingga tahan cron yang terlewat maupun terlambat.
 */
final class AppointmentDigestCommand extends Command
{
    protected $signature = 'sikg:appointments:digest';

    protected $description = 'Terbitkan ringkasan janji hari ini dan besok untuk pemilik dan staf klinik';

    public function handle(AppointmentDigest $digest, TenantContext $tenant): int
    {
        $raised = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $raised += $tenant->runAs((string) $clinicId, null, fn () => $digest->raise());
        }
        $this->info("Ringkasan janji baru diterbitkan: {$raised}");

        return self::SUCCESS;
    }
}
