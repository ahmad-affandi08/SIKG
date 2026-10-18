<?php

declare(strict_types=1);

namespace App\Setup;

use Src\Billing\Infrastructure\Seeders\ExpenseCategorySeeder;
use Src\Clinic\Infrastructure\Seeders\NationalHolidaySeeder;
use Src\Clinic\Infrastructure\Seeders\PaymentMethodSeeder;
use Src\Clinic\Infrastructure\Seeders\ProcedureSeeder;
use Src\Pharmacy\Infrastructure\Seeders\MedicineSeeder;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;

/**
 * MST-10: memuat data awal untuk SATU klinik lewat seeder yang sama dengan `db:seed` (StarterDataSeeder), sehingga klinik yang
 * tidak memakai perintah apa pun tetap dapat melakukannya dari halaman Penyiapan awal. Aman dijalankan berulang.
 */
final readonly class InstallStarterData
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{payment_methods: int, expense_categories: int, procedures: int, medicines: int, holidays: int}
     */
    public function __invoke(string $actorId, string $clinicId): array
    {
        $result = [
            'payment_methods' => (new PaymentMethodSeeder)->seedClinic($clinicId),
            'expense_categories' => (new ExpenseCategorySeeder)->seedClinic($clinicId),
            'procedures' => (new ProcedureSeeder)->seedClinic($clinicId),
            'medicines' => (new MedicineSeeder)->seedClinic($clinicId),
            'holidays' => (new NationalHolidaySeeder)->seedClinic($clinicId),
        ];

        $this->audit->record(new AuditEntry($actorId, 'setup.starter_data_loaded', 'clinic', $clinicId, null, $result));

        return $result;
    }
}
