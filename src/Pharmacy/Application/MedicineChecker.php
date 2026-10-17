<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Domain\InvalidPharmacyData;

/**
 * Memastikan obat ada (dan aktif) sebelum stok disentuh.
 */
final readonly class MedicineChecker
{
    public function __construct(private MedicineCatalog $catalog) {}

    public function assertExists(string $medicineId): void
    {
        $medicine = $this->catalog->find($medicineId);
        if ($medicine === null || ! $medicine->active) {
            throw new InvalidPharmacyData('stock.medicine_not_found', 'Obat tidak ditemukan atau sudah nonaktif.');
        }
    }
}
