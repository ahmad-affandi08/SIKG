<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use Src\Pharmacy\Domain\ProcedureMaterial;
use Src\Pharmacy\Domain\ProcedureMaterialRepository;
use Src\Pharmacy\Infrastructure\Eloquent\ProcedureMaterialModel;

final class EloquentProcedureMaterialRepository implements ProcedureMaterialRepository
{
    public function activeFor(string $procedureId): array
    {
        return array_values(ProcedureMaterialModel::query()->where('procedure_id', $procedureId)->where('is_active', true)->orderBy('id')->get()
            ->map(fn (ProcedureMaterialModel $m) => $this->record($m))->all());
    }

    public function all(): array
    {
        return array_values(ProcedureMaterialModel::query()->orderBy('procedure_id')->orderBy('id')->get()
            ->map(fn (ProcedureMaterialModel $m) => $this->record($m))->all());
    }

    public function set(string $actorId, string $procedureId, string $medicineId, int $quantity): ProcedureMaterial
    {
        $m = ProcedureMaterialModel::query()->where('procedure_id', $procedureId)->where('medicine_id', $medicineId)->first() ?? new ProcedureMaterialModel;
        if (! $m->exists) {
            $m->forceFill(['procedure_id' => $procedureId, 'medicine_id' => $medicineId, 'created_by' => $actorId]);
        }
        $m->forceFill(['quantity' => $quantity, 'is_active' => true, 'updated_by' => $actorId])->save();

        return $this->record($m);
    }

    public function find(string $id): ?ProcedureMaterial
    {
        $m = ProcedureMaterialModel::query()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function deactivate(string $actorId, string $id): void
    {
        ProcedureMaterialModel::query()->whereKey($id)->update(['is_active' => false, 'updated_by' => $actorId]);
    }

    private function record(ProcedureMaterialModel $m): ProcedureMaterial
    {
        return new ProcedureMaterial($m->id, $m->procedure_id, $m->medicine_id, $m->quantity, $m->is_active);
    }
}
