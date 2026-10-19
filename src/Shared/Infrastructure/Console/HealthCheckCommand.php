<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Backup\BackupService;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\HealthSnapshot;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;
use Src\Shared\Application\TenantContext;

/**
 * SYS-07: dijalankan scheduler tiap beberapa menit. Mencatat peringatan (tanpa PHI) dan memberi tahu staf berizin pengaturan
 * (dalam aplikasi dan WhatsApp staf klinik) hanya saat keadaan berubah, agar tidak membanjiri. Bila cron mati total,
 * pemantau eksternal ke /health yang mendeteksinya (docs/operations.md).
 */
final class HealthCheckCommand extends Command
{
    protected $signature = 'sikg:health-check';

    protected $description = 'Periksa denyut cron dan antrean; kirim peringatan saat memburuk';

    public function handle(HealthMonitor $monitor, BackupService $backups, StaffAlerts $alerts, TenantContext $tenant): int
    {
        $this->checkBackups($backups, $alerts, $tenant);

        $snapshot = $monitor->snapshot();
        $previous = Cache::get('sikg.health.status');
        Cache::forever('sikg.health.status', $snapshot->status);

        if ($snapshot->status === HealthSnapshot::OK) {
            $this->info('Sehat.');

            return self::SUCCESS;
        }

        Log::warning('Kesehatan sistem menurun', $snapshot->toArray());
        $this->warn('Menurun: '.json_encode($snapshot->toArray()));

        if ($previous !== HealthSnapshot::DEGRADED) {
            $this->alertClinics($alerts, $tenant, 'system.health', 'Kesehatan sistem menurun', 'Denyut cron atau antrean terlambat. Periksa cron hosting dan halaman Sistem.');
        }

        return self::SUCCESS;
    }

    /**
     * SYS-02: peringatan cadangan gagal atau terlambat, dikirim hanya saat keadaan berubah menjadi gagal.
     */
    private function checkBackups(BackupService $backups, StaffAlerts $alerts, TenantContext $tenant): void
    {
        $status = $backups->status();
        $previous = Cache::get('sikg.backup.state');
        Cache::forever('sikg.backup.state', $status['state']);
        if ($status['state'] !== 'fail') {
            return;
        }

        Log::warning('Cadangan data bermasalah', ['reasons' => $status['reasons']]);
        if ($previous !== 'fail') {
            $this->alertClinics($alerts, $tenant, 'system.backup', 'Cadangan data bermasalah', implode('; ', $status['reasons']));
        }
    }

    /**
     * Peringatan sistem berlaku untuk semua klinik; masing-masing diberi tahu lewat saluran stafnya sendiri. Hanya alasan teknis, tanpa PHI.
     *
     * @param  non-empty-string  $kind
     */
    private function alertClinics(StaffAlerts $alerts, TenantContext $tenant, string $kind, string $title, string $body): void
    {
        foreach (DB::table('clinics')->whereNull('deleted_at')->pluck('id') as $clinicId) {
            $tenant->runAs((string) $clinicId, null, fn () => $alerts->raise(new Notification(
                $kind, $title, $body, '/settings/system', 'setting.manage', $kind.':'.now()->format('YmdHisu'),
            )));
        }
    }
}
