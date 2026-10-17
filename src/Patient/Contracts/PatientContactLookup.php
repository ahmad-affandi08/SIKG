<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

interface PatientContactLookup
{
    /**
     * Kontak pasien yang menyetujui pengingat dan memiliki sedikitnya satu saluran.
     *
     * @param  list<string>  $patientIds
     * @return array<string, PatientContact> patient_id => kontak
     */
    public function reminderContacts(array $patientIds): array;
}
