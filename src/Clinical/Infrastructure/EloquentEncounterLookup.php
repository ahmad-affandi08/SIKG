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
}
