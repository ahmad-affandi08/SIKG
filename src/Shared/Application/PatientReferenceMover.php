<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Titik sambung penggabungan pasien (PAS-07): tiap konteks yang menyimpan `patient_id` memindahkan data miliknya sendiri dari
 * pasien ganda ke pasien utama di dalam transaksi penggabungan. Konteks Patient hanya mengenal antarmuka ini, bukan tabel
 * konteks lain (M2). Tidak ada baris yang dihapus; hanya kepemilikan yang berpindah (M5).
 */
interface PatientReferenceMover
{
    /** Nama data yang dipindahkan untuk pratinjau, contoh "Janji temu". */
    public function label(): string;

    /** Jumlah baris milik pasien ini yang akan dipindahkan. */
    public function count(string $patientId): int;

    /** Memindahkan seluruh baris dari pasien ganda ke pasien utama; mengembalikan jumlah baris. */
    public function move(string $fromPatientId, string $toPatientId): int;
}
