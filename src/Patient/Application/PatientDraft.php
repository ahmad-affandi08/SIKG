<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use DateTimeImmutable;
use Src\Patient\Domain\EmergencyContact;
use Src\Patient\Domain\Guardian;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\Nik;
use Src\Patient\Domain\PatientProfile;
use Src\Patient\Domain\PhoneNumber;
use Src\Patient\Domain\Sex;

/**
 * Masukan mentah (teks) dari formulir atau berkas impor. `toProfile` memeriksa semuanya dengan aturan domain.
 */
final readonly class PatientDraft
{
    public function __construct(
        public string $name,
        public ?string $nik,
        public ?string $nikMissingReason,
        public string $birthDate,
        public string $sex,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $occupation = null,
        public ?string $emergencyName = null,
        public ?string $emergencyRelation = null,
        public ?string $emergencyPhone = null,
        public ?string $guardianName = null,
        public ?string $guardianRelation = null,
        public ?string $guardianPhone = null,
    ) {}

    public function toProfile(DateTimeImmutable $today): PatientProfile
    {
        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $this->birthDate);
        if ($birth === false || $birth->format('Y-m-d') !== $this->birthDate) {
            throw new InvalidPatientData('patient.invalid_birth_date', 'Tanggal lahir tidak valid.');
        }
        $sex = Sex::tryFrom($this->sex) ?? throw new InvalidPatientData('patient.invalid_sex', 'Jenis kelamin tidak valid.');

        $emergency = $this->filled($this->emergencyName) || $this->filled($this->emergencyPhone)
            ? new EmergencyContact((string) $this->emergencyName, (string) $this->emergencyRelation, new PhoneNumber((string) $this->emergencyPhone))
            : null;
        $guardian = $this->filled($this->guardianName)
            ? new Guardian((string) $this->guardianName, (string) $this->guardianRelation, $this->filled($this->guardianPhone) ? new PhoneNumber((string) $this->guardianPhone) : null)
            : null;

        return new PatientProfile(
            name: $this->name,
            nik: $this->filled($this->nik) ? new Nik((string) $this->nik) : null,
            nikMissingReason: $this->nikMissingReason,
            birthDate: $birth,
            sex: $sex,
            address: $this->clean($this->address),
            phone: $this->filled($this->phone) ? new PhoneNumber((string) $this->phone) : null,
            occupation: $this->clean($this->occupation),
            emergencyContact: $emergency,
            guardian: $guardian,
            today: $today,
        );
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function clean(?string $value): ?string
    {
        return $this->filled($value) ? trim((string) $value) : null;
    }
}
