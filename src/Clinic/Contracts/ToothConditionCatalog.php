<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kontrak daftar kondisi gigi untuk Clinical (odontogram). Daftar dikelola Admin di pengaturan.
 */
interface ToothConditionCatalog
{
    public function find(string $code): ?ToothCondition;

    /**
     * @return list<ToothCondition> semua kondisi termasuk nonaktif (riwayat tetap dapat ditampilkan)
     */
    public function all(): array;
}
