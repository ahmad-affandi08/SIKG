<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

interface PatientPhoneLookup
{
    /**
     * @param  list<string>  $patientIds
     * @return array<string, PatientPhone> patient_id => nama dan nomor HP (pasien tanpa nomor tetap ada dengan phone null)
     */
    public function phones(array $patientIds): array;
}
