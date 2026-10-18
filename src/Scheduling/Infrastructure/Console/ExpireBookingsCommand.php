<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Scheduling\Application\Booking\BookingDesk;
use Src\Shared\Application\TenantContext;

/**
 * P3-01: permintaan booking online yang melewati masa tahan menjadi kedaluwarsa dan slotnya terbuka lagi. Aman diulang.
 */
final class ExpireBookingsCommand extends Command
{
    protected $signature = 'sikg:booking:expire';

    protected $description = 'Kedaluwarsakan permintaan booking online yang melewati masa tahan';

    public function handle(BookingDesk $desk, TenantContext $tenant): int
    {
        $expired = 0;
        foreach (DB::table('clinics')->pluck('id') as $clinicId) {
            $expired += $tenant->runAs((string) $clinicId, null, fn () => $desk->expireDue());
        }
        $this->info("Permintaan booking kedaluwarsa: {$expired}");

        return self::SUCCESS;
    }
}
