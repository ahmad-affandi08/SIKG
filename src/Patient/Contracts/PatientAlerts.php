<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

/**
 * Kontrak untuk Clinical dan Pharmacy: peringatan medis pasien (alergi dan penyakit sistemik, PAS-04) untuk banner
 * di layar pemeriksaan dan pengecekan resep (OBT-02). Konteks lain tidak membaca tabel Patient langsung.
 */
interface PatientAlerts
{
    /**
     * Alergi dan penyakit sistemik, alergi lebih dulu.
     *
     * @return list<PatientAlert>
     */
    public function warnings(string $patientId): array;

    /**
     * Seluruh riwayat medis (termasuk obat rutin dan kehamilan) untuk layar anamnesis.
     *
     * @return list<PatientAlert>
     */
    public function history(string $patientId): array;
}
