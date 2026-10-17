<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kontrak katalog tindakan untuk konteks lain (Clinical, Billing). Hanya tindakan aktif yang dapat dipilih.
 */
interface ProcedureCatalog
{
    public function find(string $procedureId): ?ProcedureSnapshot;

    /**
     * @return list<ProcedureSnapshot> maksimal 20 hasil, kode lebih dulu
     */
    public function search(string $term, int $limit = 20): array;
}
