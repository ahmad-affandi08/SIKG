<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use Src\Clinical\Contracts\EncounterInfo;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;

final class EloquentEncounterLookup implements EncounterLookup
{
    public function find(string $encounterId): ?EncounterInfo
    {
        $e = EncounterModel::query()->find($encounterId);

        return $e === null ? null : new EncounterInfo($e->id, $e->patient_id, $e->doctor_id, $e->status === 'open');
    }

    public function openForPatient(string $patientId): array
    {
        return array_values(EncounterModel::query()->where('patient_id', $patientId)->where('status', 'open')->orderByDesc('started_at')->orderByDesc('id')->get()
            ->map(fn (EncounterModel $e) => new EncounterInfo($e->id, $e->patient_id, $e->doctor_id, true))->all());
    }
}
