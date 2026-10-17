<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use DateTimeImmutable;
use Src\Clinical\Contracts\RecallCandidate;
use Src\Clinical\Contracts\RecallLookup;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;

final class EloquentRecallLookup implements RecallLookup
{
    public function latestClosedBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return array_values(EncounterModel::query()
            ->where('status', 'closed')->whereNotNull('closed_at')->where('closed_at', '>=', $from)->where('closed_at', '<', $to)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('encounters as later')
                ->whereColumn('later.patient_id', 'encounters.patient_id')->where('later.status', 'closed')->whereNull('later.deleted_at')
                ->whereColumn('later.closed_at', '>', 'encounters.closed_at'))
            ->orderBy('closed_at')->orderBy('id')->get()
            ->map(fn (EncounterModel $e) => new RecallCandidate($e->id, $e->patient_id, $e->doctor_id, $e->closed_at?->toDateTimeImmutable() ?? $to))->all());
    }
}
