<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Clinical\Application\Imaging\ImagingService;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;
use Src\Shared\Application\TenantContext;

/**
 * P3-03: memeriksa folder masuk gambar rontgen tiap klinik dan memberi tahu petugas bila ada berkas menunggu dikaitkan ke kunjungan.
 * Pemberitahuan terbit sekali per jumlah berkas per hari (kunci dedupe), sehingga aman dijalankan tiap beberapa menit oleh cron.
 */
final class ScanImagingInboxCommand extends Command
{
    protected $signature = 'sikg:imaging:scan';

    protected $description = 'Periksa folder masuk rontgen digital dan beri tahu petugas';

    public function handle(ImagingService $imaging, StaffAlerts $alerts, TenantContext $tenant): int
    {
        $raised = 0;
        foreach (DB::table('clinics')->whereNull('deleted_at')->pluck('id') as $clinicId) {
            $count = $tenant->runAs((string) $clinicId, null, function () use ($imaging, $alerts): int {
                $waiting = $imaging->pendingCount();
                if ($waiting > 0) {
                    $alerts->raise(new Notification(
                        'imaging.inbox',
                        'Rontgen masuk',
                        "{$waiting} gambar rontgen menunggu dikaitkan ke kunjungan.",
                        '/imaging/inbox',
                        'encounter.write',
                        'imaging.inbox:'.now()->format('Ymd').':'.$waiting,
                    ));
                }

                return $waiting;
            });
            $raised += (int) $count;
        }
        $this->info("Gambar menunggu: {$raised}");

        return self::SUCCESS;
    }
}
