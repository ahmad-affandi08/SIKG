<?php

declare(strict_types=1);

namespace Src\Clinical\Application\Diagnosis;

/**
 * MST-07: referensi diagnosa ICD-10 yang dicari saat mengisi diagnosa kunjungan.
 */
interface DiagnosisCatalog
{
    /**
     * Hanya kode aktif. Cocok pada awal kode atau potongan nama; kode lebih dulu.
     *
     * @return list<DiagnosisSummary>
     */
    public function search(string $term, int $limit = 20): array;
}
