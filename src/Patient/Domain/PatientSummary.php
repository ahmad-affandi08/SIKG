<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Baris hasil pencarian: hanya yang perlu untuk memilih pasien. NIK tampil penuh (petugas berizin pasien.lihat, keputusan pemilik produk); nomor HP tersamar.
 */
final readonly class PatientSummary
{
    public function __construct(
        public string $id,
        public string $medicalRecordNo,
        public string $name,
        public string $birthDate,
        public string $sex,
        public ?string $phoneMasked,
        public ?string $nik = null,
    ) {}

    /**
     * @return array{id: string, medical_record_no: string, name: string, birth_date: string, sex: string, phone: ?string, nik: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'medical_record_no' => $this->medicalRecordNo,
            'name' => $this->name,
            'birth_date' => $this->birthDate,
            'sex' => $this->sex,
            'phone' => $this->phoneMasked,
            'nik' => $this->nik,
        ];
    }
}
