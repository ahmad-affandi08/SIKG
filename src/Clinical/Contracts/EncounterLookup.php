<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

/**
 * Kontrak baca kunjungan bagi konteks lain (Pharmacy membuat resep, Billing menagih) tanpa membaca tabel Clinical.
 */
interface EncounterLookup
{
    public function find(string $encounterId): ?EncounterInfo;

    /**
     * Kunjungan pasien yang masih terbuka (sedang berjalan), terbaru dulu.
     *
     * @return list<EncounterInfo>
     */
    public function openForPatient(string $patientId): array;
}
