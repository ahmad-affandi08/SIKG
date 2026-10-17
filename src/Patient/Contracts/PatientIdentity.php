<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

/**
 * Identitas pasien untuk pelaporan ke platform interoperabilitas (P2-01). Memuat NIK terdekripsi: hanya dipakai untuk
 * pencarian ID pasien di SATUSEHAT, tidak pernah ditulis ke log atau disimpan ulang oleh pemanggil.
 */
final readonly class PatientIdentity
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $nik,
        public string $birthDate,
        public string $sex,
    ) {}
}
