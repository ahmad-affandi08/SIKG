<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Contracts\PatientLookup;
use Src\Patient\Contracts\PatientSummary;
use Src\Patient\Infrastructure\Eloquent\PatientModel;

final class EloquentPatientLookup implements PatientLookup
{
    public function exists(string $patientId): bool
    {
        return PatientModel::query()->whereKey($patientId)->exists();
    }

    public function summaries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (PatientModel::withTrashed()->whereKey(array_values(array_unique($ids)))->get() as $p) {
            $out[$p->id] = new PatientSummary($p->id, $p->medical_record_no, $p->name, $p->birth_date->toDateString());
        }

        return $out;
    }
}
