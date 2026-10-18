<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Billing\Application\Online\OnlinePayments;
use Src\Shared\Application\TenantContext;

/**
 * P3-05: rekonsiliasi pembayaran online. Memeriksa ke penyedia setiap QR yang masih menunggu: yang sudah dibayar dicatat ke tagihan,
 * yang kedaluwarsa atau ditolak ditutup. Menjadi jaring pengaman bila webhook terlewat; aman diulang (idempoten).
 */
final class ReconcileOnlinePaymentsCommand extends Command
{
    protected $signature = 'sikg:payments:reconcile';

    protected $description = 'Rekonsiliasi QR pembayaran online yang masih menunggu dengan status di penyedia';

    public function handle(OnlinePayments $online, TenantContext $tenant): int
    {
        $total = ['checked' => 0, 'paid' => 0, 'closed' => 0, 'failed' => 0];
        foreach (DB::table('online_payment_settings')->whereNull('deleted_at')->pluck('clinic_id') as $clinicId) {
            $result = $tenant->runAs((string) $clinicId, null, fn () => $online->reconcile());
            if (is_array($result)) {
                foreach ($total as $key => $value) {
                    $total[$key] = $value + (int) ($result[$key] ?? 0);
                }
            }
        }
        $this->info("Diperiksa: {$total['checked']}, lunas dicatat: {$total['paid']}, ditutup: {$total['closed']}, gagal diperiksa: {$total['failed']}");

        return self::SUCCESS;
    }
}
