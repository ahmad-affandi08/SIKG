<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Contracts\PatientAlert;
use Src\Patient\Contracts\PatientAlerts;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Infrastructure\Eloquent\PatientMedicalHistoryModel;

final class EloquentPatientAlerts implements PatientAlerts
{
    public function warnings(string $patientId): array
    {
        return array_values(array_filter(
            $this->history($patientId),
            fn (PatientAlert $a) => in_array($a->type, [MedicalHistoryType::Allergy->value, MedicalHistoryType::SystemicDisease->value], true),
        ));
    }

    public function history(string $patientId): array
    {
        $order = [MedicalHistoryType::Allergy->value => 0, MedicalHistoryType::SystemicDisease->value => 1, MedicalHistoryType::Pregnancy->value => 2, MedicalHistoryType::RegularMedication->value => 3];
        $rows = PatientMedicalHistoryModel::query()->where('patient_id', $patientId)->get()
            ->sortBy(fn (PatientMedicalHistoryModel $h) => [$order[$h->type] ?? 9, $h->name])->values();

        return array_values($rows->map(fn (PatientMedicalHistoryModel $h) => new PatientAlert(
            $h->type,
            MedicalHistoryType::from($h->type)->label(),
            $h->name,
            $h->severity,
        ))->all());
    }
}
