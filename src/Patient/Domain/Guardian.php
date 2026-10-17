<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Wali atau penanggung jawab pasien (wajib untuk pasien di bawah 18 tahun, PAS-06).
 */
final readonly class Guardian
{
    public string $name;

    public string $relation;

    public function __construct(string $name, string $relation, public ?PhoneNumber $phone = null)
    {
        $this->name = trim($name);
        $this->relation = trim($relation);
        if ($this->name === '' || mb_strlen($this->name) > 255) {
            throw new InvalidPatientData('patient.invalid_guardian', 'Nama wali wajib diisi.');
        }
        if ($this->relation === '' || mb_strlen($this->relation) > 50) {
            throw new InvalidPatientData('patient.invalid_guardian', 'Hubungan wali dengan pasien wajib diisi.');
        }
    }
}
