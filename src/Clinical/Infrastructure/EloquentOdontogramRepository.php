<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use DateTimeImmutable;
use Src\Clinical\Domain\Dentition;
use Src\Clinical\Domain\Odontogram;
use Src\Clinical\Domain\OdontogramRepository;
use Src\Clinical\Domain\Surface;
use Src\Clinical\Domain\ToothStateEntry;
use Src\Clinical\Infrastructure\Eloquent\OdontogramSnapshotModel;
use Src\Clinical\Infrastructure\Eloquent\OdontogramToothStateModel;

final class EloquentOdontogramRepository implements OdontogramRepository
{
    public function snapshotFor(string $actorId, string $patientId, string $encounterId, DateTimeImmutable $at): string
    {
        $existing = OdontogramSnapshotModel::query()->where('encounter_id', $encounterId)->first();
        if ($existing !== null) {
            return $existing->id;
        }

        $snapshot = new OdontogramSnapshotModel;
        $snapshot->forceFill(['patient_id' => $patientId, 'encounter_id' => $encounterId, 'dentition' => Dentition::Permanent->value, 'taken_at' => $at, 'created_by' => $actorId])->save();

        return $snapshot->id;
    }

    public function setState(string $actorId, string $snapshotId, int $toothFdi, Surface $surface, string $conditionCode, DateTimeImmutable $at): ?string
    {
        $row = OdontogramToothStateModel::query()->where('snapshot_id', $snapshotId)->where('tooth_fdi', $toothFdi)->where('surface', $surface->value)->lockForUpdate()->first();
        if ($row === null) {
            (new OdontogramToothStateModel)->forceFill([
                'snapshot_id' => $snapshotId,
                'tooth_fdi' => $toothFdi,
                'surface' => $surface->value,
                'condition_code' => $conditionCode,
                'set_by' => $actorId,
                'set_at' => $at,
            ])->save();

            return null;
        }

        $previous = $row->condition_code;
        $row->forceFill(['condition_code' => $conditionCode, 'set_by' => $actorId, 'set_at' => $at])->save();

        return $previous;
    }

    public function refreshDentition(string $snapshotId): void
    {
        $teeth = OdontogramToothStateModel::query()->where('snapshot_id', $snapshotId)->distinct()->pluck('tooth_fdi')->map(fn ($v) => (int) $v)->all();
        OdontogramSnapshotModel::query()->whereKey($snapshotId)->update(['dentition' => Odontogram::dentitionOf(array_values($teeth))->value]);
    }

    public function entriesForPatient(string $patientId): array
    {
        $snapshots = OdontogramSnapshotModel::query()->where('patient_id', $patientId)->pluck('encounter_id', 'id')->all();
        if ($snapshots === []) {
            return [];
        }

        return array_values(OdontogramToothStateModel::query()->whereIn('snapshot_id', array_keys($snapshots))->get()
            ->map(fn (OdontogramToothStateModel $s) => $this->entry($s, (string) $snapshots[$s->snapshot_id]))->all());
    }

    public function entriesForEncounter(string $encounterId): array
    {
        $snapshot = OdontogramSnapshotModel::query()->where('encounter_id', $encounterId)->first();
        if ($snapshot === null) {
            return [];
        }

        return array_values(OdontogramToothStateModel::query()->where('snapshot_id', $snapshot->id)->get()
            ->map(fn (OdontogramToothStateModel $s) => $this->entry($s, $encounterId))->all());
    }

    private function entry(OdontogramToothStateModel $s, string $encounterId): ToothStateEntry
    {
        return new ToothStateEntry($s->id, $s->tooth_fdi, Surface::from($s->surface), $s->condition_code, $s->set_at->toDateTimeImmutable(), $encounterId);
    }
}
