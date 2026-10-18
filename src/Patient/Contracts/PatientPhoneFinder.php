<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

/**
 * Mencari pasien lewat nomor HP untuk konteks lain (booking online, P3-01). Nomor dinormalkan ke bentuk internasional tanpa plus;
 * pencarian memakai blind index sehingga nomor tidak pernah dibandingkan sebagai teks terang di basis data.
 */
interface PatientPhoneFinder
{
    /** Bentuk normal (628123456789) atau null bila bukan nomor HP Indonesia yang sah. */
    public function normalize(string $input): ?string;

    /**
     * @return list<PatientSummary> pasien yang memakai nomor itu (tanpa data lain)
     */
    public function findByPhone(string $normalizedPhone): array;
}
