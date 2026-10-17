<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Pasien yang mungkin sama dengan yang sedang didaftarkan. `reason`: `nik` (NIK sama) atau `similar`
 * (tanggal lahir sama dengan nama mirip atau nomor HP sama).
 */
final readonly class DuplicateCandidate
{
    public function __construct(
        public string $id,
        public string $medicalRecordNo,
        public string $name,
        public string $birthDate,
        public string $reason,
    ) {}

    /**
     * @return array{id: string, medical_record_no: string, name: string, birth_date: string, reason: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'medical_record_no' => $this->medicalRecordNo, 'name' => $this->name, 'birth_date' => $this->birthDate, 'reason' => $this->reason];
    }
}
