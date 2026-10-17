<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use DateTimeImmutable;

/**
 * Data identitas pasien yang sah menurut aturan domain: NIK wajib (atau alasan tertulis bila belum ada, PAS-01),
 * pasien di bawah 18 tahun wajib punya wali (PAS-06), tanggal lahir tidak di masa depan. `validate: false` hanya untuk
 * memuat data yang sudah tersimpan (aturan bisa berubah sesudah data dibuat).
 */
final readonly class PatientProfile
{
    public const ADULT_AGE = 18;

    public string $name;

    public ?string $nikMissingReason;

    public function __construct(
        string $name,
        public ?Nik $nik,
        ?string $nikMissingReason,
        public DateTimeImmutable $birthDate,
        public Sex $sex,
        public ?string $address,
        public ?PhoneNumber $phone,
        public ?string $occupation,
        public ?EmergencyContact $emergencyContact,
        public ?Guardian $guardian,
        DateTimeImmutable $today,
        bool $validate = true,
    ) {
        $this->name = trim($name);
        $this->nikMissingReason = $nikMissingReason !== null && trim($nikMissingReason) !== '' ? trim($nikMissingReason) : null;

        if (! $validate) {
            return;
        }

        if ($this->name === '' || mb_strlen($this->name) > 255) {
            throw new InvalidPatientData('patient.invalid_name', 'Nama pasien wajib diisi (maksimal 255 karakter).');
        }
        if ($birthDate->format('Y-m-d') > $today->format('Y-m-d')) {
            throw new InvalidPatientData('patient.invalid_birth_date', 'Tanggal lahir tidak boleh di masa depan.');
        }
        if ($nik === null && $this->nikMissingReason === null) {
            throw new InvalidPatientData('patient.nik_required', 'NIK wajib diisi. Bila belum ada (contoh bayi atau WNA), tuliskan alasannya.');
        }
        if ($nik !== null && $this->nikMissingReason !== null) {
            throw new InvalidPatientData('patient.nik_reason_conflict', 'Alasan NIK belum ada hanya diisi bila NIK kosong.');
        }
        if ($this->age($today) < self::ADULT_AGE && $guardian === null) {
            throw new InvalidPatientData('patient.guardian_required', 'Pasien di bawah 18 tahun wajib memiliki data wali atau penanggung jawab.');
        }
    }

    public function age(DateTimeImmutable $today): int
    {
        return $this->birthDate->diff($today)->y;
    }

    /**
     * Ringkasan untuk audit: NIK dan nomor HP tersamar agar audit tidak menjadi salinan PHI sensitif.
     *
     * @return array<string, mixed>
     */
    public function auditView(): array
    {
        return [
            'name' => $this->name,
            'nik' => $this->nik?->masked(),
            'nik_missing_reason' => $this->nikMissingReason,
            'birth_date' => $this->birthDate->format('Y-m-d'),
            'sex' => $this->sex->value,
            'address' => $this->address,
            'phone' => $this->phone?->masked(),
            'occupation' => $this->occupation,
            'emergency_contact' => $this->emergencyContact === null ? null : [
                'name' => $this->emergencyContact->name,
                'relation' => $this->emergencyContact->relation,
                'phone' => $this->emergencyContact->phone->masked(),
            ],
            'guardian' => $this->guardian === null ? null : [
                'name' => $this->guardian->name,
                'relation' => $this->guardian->relation,
                'phone' => $this->guardian->phone?->masked(),
            ],
        ];
    }
}
