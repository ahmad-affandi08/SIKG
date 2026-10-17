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

    /**
     * Kode ICD-9-CM tindakan untuk pelaporan SATUSEHAT (P2-01); tindakan tanpa kode tidak ada di hasil.
     *
     * @param  list<string>  $procedureIds
     * @return array<string, string> procedure_id => kode ICD-9-CM
     */
    public function icd9Codes(array $procedureIds): array;
}
