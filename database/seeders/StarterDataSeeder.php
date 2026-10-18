<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Billing\Infrastructure\Seeders\ExpenseCategorySeeder;
use Src\Clinic\Infrastructure\Seeders\NationalHolidaySeeder;
use Src\Clinic\Infrastructure\Seeders\PaymentMethodSeeder;
use Src\Clinic\Infrastructure\Seeders\ProcedureSeeder;
use Src\Pharmacy\Infrastructure\Seeders\MedicineSeeder;

/**
 * Data awal klinik (tanpa data pasien, M12): kategori biaya, metode pembayaran, katalog tindakan dan obat (nonaktif, tarif
 * dan harga 0 sampai klinik mengisinya), dan hari libur nasional bertanggal tetap. Berlaku untuk setiap klinik yang sudah ada
 * dan aman dijalankan berulang. Tanpa klinik, tidak melakukan apa pun.
 */
class StarterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ExpenseCategorySeeder::class,
            PaymentMethodSeeder::class,
            ProcedureSeeder::class,
            MedicineSeeder::class,
            NationalHolidaySeeder::class,
        ]);
    }
}
