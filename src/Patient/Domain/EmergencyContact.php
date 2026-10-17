<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

final readonly class EmergencyContact
{
    public string $name;

    public string $relation;

    public function __construct(string $name, string $relation, public PhoneNumber $phone)
    {
        $this->name = trim($name);
        $this->relation = trim($relation);
        if ($this->name === '' || mb_strlen($this->name) > 255 || mb_strlen($this->relation) > 50) {
            throw new InvalidPatientData('patient.invalid_emergency_contact', 'Data kontak darurat tidak valid.');
        }
    }
}
