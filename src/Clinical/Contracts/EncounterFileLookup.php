<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

interface EncounterFileLookup
{
    /**
     * Berkas gambar (foto dan rontgen) per kunjungan.
     *
     * @param  list<string>  $encounterIds
     * @return array<string, list<EncounterFileSummary>> encounter_id => berkas
     */
    public function imagesFor(array $encounterIds): array;
}
