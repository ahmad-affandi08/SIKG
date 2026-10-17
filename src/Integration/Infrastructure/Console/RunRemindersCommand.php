<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Integration\Application\Reminder\PlanReminders;
use Src\Integration\Application\Reminder\SendReminders;
use Src\Shared\Application\TenantContext;

/**
 * P2-03: menyiapkan pengingat yang jatuh tempo lalu, bila mode API aktif, mengirimnya. Dijadwalkan tiap 15 menit; idempoten sehingga
 * aman diulang dan tahan cron yang terlewat.
 */
final class RunRemindersCommand extends Command
{
    protected $signature = 'sikg:reminders:run';

    protected $description = 'Siapkan pengingat pasien (H-1, hari-H, kontrol berkala) dan kirim yang berbasis API';

    public function handle(PlanReminders $plan, SendReminders $send, TenantContext $tenant): int
    {
        $created = 0;
        $sent = 0;
        $failed = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $created += $tenant->runAs((string) $clinicId, null, fn () => $plan->run());
            $result = $tenant->runAs((string) $clinicId, null, fn () => $send->run());
            $sent += $result['sent'];
            $failed += $result['failed'];
        }
        $this->info("Pengingat baru: {$created}, terkirim lewat API: {$sent}, gagal: {$failed}");

        return self::SUCCESS;
    }
}
