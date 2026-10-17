<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Satu catatan riwayat medis (PAS-04): alergi, penyakit sistemik, obat rutin, atau kehamilan.
 * Alergi dan penyakit sistemik tampil sebagai banner peringatan di layar pemeriksaan.
 */
final readonly class MedicalHistoryEntry
{
    public string $name;

    public ?string $notes;

    public function __construct(public MedicalHistoryType $type, string $name, public ?Severity $severity = null, ?string $notes = null)
    {
        $this->name = trim($name);
        $this->notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;
        if ($this->name === '' || mb_strlen($this->name) > 255) {
            throw new InvalidPatientData('patient.invalid_history', 'Nama riwayat medis wajib diisi.');
        }
        if ($this->notes !== null && mb_strlen($this->notes) > 1000) {
            throw new InvalidPatientData('patient.invalid_history', 'Catatan riwayat terlalu panjang.');
        }
    }

    public function isWarning(): bool
    {
        return $this->type === MedicalHistoryType::Allergy || $this->type === MedicalHistoryType::SystemicDisease;
    }
}
