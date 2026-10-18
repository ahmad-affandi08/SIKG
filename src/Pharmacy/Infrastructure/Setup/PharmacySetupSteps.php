<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure\Setup;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Setup\SetupStep;
use Src\Shared\Application\Setup\SetupStepProvider;

/**
 * MST-10: langkah Penyiapan awal yang datanya dimiliki konteks Pharmacy (katalog obat dan stok awal).
 */
final class PharmacySetupSteps implements SetupStepProvider
{
    public function steps(string $clinicId): array
    {
        $count = fn (bool $activeOnly): int => (int) DB::table('medicines')->where('clinic_id', $clinicId)->whereNull('deleted_at')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))->count();
        $active = $count(true);
        $all = $count(false);
        $movements = (int) DB::table('stock_movements')->where('clinic_id', $clinicId)->count();

        return [
            new SetupStep(60, 'medicines', 'Obat dan bahan',
                'Isi harga jual lalu aktifkan obat yang dipakai agar dapat diresepkan dan ditagih.', $active > 0, false,
                "{$active} aktif dari {$all} obat dan bahan.", '/settings/master/medicines', 'Atur obat', '/settings/master/medicines/import'),
            new SetupStep(65, 'stock_opening', 'Stok awal obat dan bahan',
                'Isi saldo stok yang ada sekarang (jumlah, batch, kedaluwarsa) agar peringatan stok dan pemakaian bahan akurat. Obat harus sudah aktif.',
                $movements > 0, false,
                $movements > 0 ? "{$movements} mutasi stok tercatat." : 'Belum ada stok tercatat.',
                '/stock', 'Lihat stok', '/settings/master/stock-opening/import'),
        ];
    }
}
