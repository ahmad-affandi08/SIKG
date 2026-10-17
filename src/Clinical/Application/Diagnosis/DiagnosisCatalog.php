<?php

declare(strict_types=1);

namespace Src\Clinical\Application\Diagnosis;

/**
 * MST-07: referensi diagnosa ICD-10 yang dicari saat mengisi diagnosa kunjungan.
 */
interface DiagnosisCatalog
{
    /**
     * Satu kode aktif menurut id, atau null bila tidak ada atau sudah nonaktif.
     */
    public function find(string $diagnosisId): ?DiagnosisSummary;

    /**
     * Hanya kode aktif. Cocok pada awal kode atau potongan nama; kode lebih dulu.
     *
     * @return list<DiagnosisSummary>
     */
    public function search(string $term, int $limit = 20): array;
}
