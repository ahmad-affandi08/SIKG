<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Setup;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Setup\SetupStep;
use Src\Shared\Application\Setup\SetupStepProvider;

/**
 * MST-10: langkah Penyiapan awal yang datanya dimiliki konteks Billing (kategori biaya operasional).
 */
final class BillingSetupSteps implements SetupStepProvider
{
    public function steps(string $clinicId): array
    {
        $categories = (int) DB::table('expense_categories')->where('clinic_id', $clinicId)->whereNull('deleted_at')
            ->where('is_active', true)->count();

        return [
            new SetupStep(70, 'expense_categories', 'Kategori biaya operasional',
                'Diperlukan untuk mencatat biaya dan laporan laba rugi.', $categories > 0, false,
                "{$categories} kategori aktif.", '/settings/master/expense-categories', 'Kelola kategori'),
        ];
    }
}
