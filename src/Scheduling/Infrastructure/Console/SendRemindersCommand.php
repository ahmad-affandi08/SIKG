<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Application\AppointmentReminders;
use Src\Shared\Application\TenantContext;

/**
 * JDW-07: mengirim pengingat janji tiap klinik. Dijadwalkan tiap 15 menit; pencatatan per janji membuatnya aman diulang
 * dan tahan cron yang terlewat.
 */
final class SendRemindersCommand extends Command
{
    protected $signature = 'sikg:appointments:remind';

    protected $description = 'Kirim pengingat janji (H-1 dan hari kunjungan) lewat email dan Telegram';

    public function handle(AppointmentReminders $reminders, TenantContext $tenant): int
    {
        $sent = 0;
        $failed = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $result = $tenant->runAs((string) $clinicId, null, fn () => $reminders->run());
            $sent += $result['sent'];
            $failed += $result['failed'];
        }
        $this->info("Pengingat terkirim: {$sent}, gagal: {$failed}");

        return self::SUCCESS;
    }
}
