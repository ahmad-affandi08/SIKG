<?php

declare(strict_types=1);

namespace Src\Pharmacy\Contracts;

/**
 * Kontrak baca resep bagi Clinical (layar pemeriksaan dan linimasa pasien, RM-11).
 */
interface PrescriptionLookup
{
    /**
     * @param  list<string>  $encounterIds
     * @return array<string, list<PrescriptionSummary>> id kunjungan => resep (termasuk yang dibatalkan)
     */
    public function forEncounters(array $encounterIds): array;
}
