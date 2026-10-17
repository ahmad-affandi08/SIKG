<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

/**
 * Kontrak untuk konteks lain (Scheduling, Clinical, Billing) yang perlu memastikan pasien ada dan menampilkan
 * namanya. Hanya ID, nomor RM, dan nama; data lain tidak dibagikan.
 */
interface PatientLookup
{
    public function exists(string $patientId): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, PatientSummary> id => ringkasan (id yang tidak ditemukan tidak ada)
     */
    public function summaries(array $ids): array;
}
