<?php

declare(strict_types=1);

namespace Src\Pharmacy\Contracts;

interface MedicineCatalog
{
    public function find(string $medicineId): ?MedicineInfo;

    /**
     * @return list<array{id: string, name: string, unit: string, sell_price_amount: int, drug_class: ?string, available: int}> maksimal 20, obat aktif
     */
    public function search(string $term, int $limit = 20): array;
}
