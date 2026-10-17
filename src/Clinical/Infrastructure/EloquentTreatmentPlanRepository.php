<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use DateTimeImmutable;
use Src\Clinical\Domain\PlanItemRecord;
use Src\Clinical\Domain\PlanItemStatus;
use Src\Clinical\Domain\PlanStatus;
use Src\Clinical\Domain\TreatmentPlanRecord;
use Src\Clinical\Domain\TreatmentPlanRepository;
use Src\Clinical\Infrastructure\Eloquent\TreatmentPlanItemModel;
use Src\Clinical\Infrastructure\Eloquent\TreatmentPlanModel;

final class EloquentTreatmentPlanRepository implements TreatmentPlanRepository
{
    public function create(string $actorId, string $patientId, string $title, ?string $notes, DateTimeImmutable $at): string
    {
        $plan = new TreatmentPlanModel;
        $plan->forceFill(['patient_id' => $patientId, 'title' => $title, 'notes' => $notes, 'status' => PlanStatus::Planned->value, 'created_by' => $actorId, 'created_at' => $at, 'updated_at' => $at])->save();

        return $plan->id;
    }

    public function find(string $planId): ?TreatmentPlanRecord
    {
        $plan = TreatmentPlanModel::query()->find($planId);

        return $plan instanceof TreatmentPlanModel ? $this->plans([$plan])[0] ?? null : null;
    }

    public function forPatient(string $patientId): array
    {
        $plans = array_values(TreatmentPlanModel::query()->where('patient_id', $patientId)->orderByDesc('created_at')->orderByDesc('id')->get()->all());

        return $this->plans($plans);
    }

    public function addItem(string $actorId, string $planId, string $procedureId, string $code, string $name, ?int $toothFdi, int $quantity, int $unitPriceAmount, int $estimateAmount, ?string $notes): string
    {
        $sequence = (int) TreatmentPlanItemModel::withTrashed()->where('plan_id', $planId)->max('sequence') + 1;
        $item = new TreatmentPlanItemModel;
        $item->forceFill([
            'plan_id' => $planId, 'sequence' => $sequence, 'procedure_id' => $procedureId, 'procedure_code' => $code, 'procedure_name' => $name,
            'tooth_fdi' => $toothFdi, 'quantity' => $quantity, 'unit_price_amount' => $unitPriceAmount, 'estimate_amount' => $estimateAmount,
            'status' => PlanItemStatus::Planned->value, 'notes' => $notes, 'created_by' => $actorId,
        ])->save();

        return $item->id;
    }

    public function findItem(string $itemId): ?PlanItemRecord
    {
        $item = TreatmentPlanItemModel::query()->find($itemId);

        return $item instanceof TreatmentPlanItemModel ? $this->item($item) : null;
    }

    public function setItemStatus(string $actorId, string $itemId, PlanItemStatus $status, ?string $encounterId, ?DateTimeImmutable $completedAt): void
    {
        $item = TreatmentPlanItemModel::query()->lockForUpdate()->findOrFail($itemId);
        $item->forceFill([
            'status' => $status->value,
            'encounter_id' => $encounterId,
            'completed_at' => $status === PlanItemStatus::Done ? $completedAt : null,
            'updated_by' => $actorId,
        ])->save();
    }

    public function removeItem(string $actorId, string $itemId): void
    {
        $item = TreatmentPlanItemModel::query()->lockForUpdate()->findOrFail($itemId);
        $item->forceFill(['updated_by' => $actorId])->save();
        $item->delete();
    }

    public function setStatus(string $actorId, string $planId, PlanStatus $status): void
    {
        TreatmentPlanModel::query()->whereKey($planId)->update(['status' => $status->value, 'updated_by' => $actorId, 'updated_at' => now()]);
    }

    public function openItemsForPatient(string $patientId): array
    {
        $plans = TreatmentPlanModel::query()->where('patient_id', $patientId)->whereIn('status', [PlanStatus::Planned->value, PlanStatus::InProgress->value])->pluck('id')->all();
        if ($plans === []) {
            return [];
        }
        $items = TreatmentPlanItemModel::query()->whereIn('plan_id', $plans)->whereIn('status', [PlanItemStatus::Planned->value, PlanItemStatus::InProgress->value])->orderBy('plan_id')->orderBy('sequence')->get();

        return array_values($items->map(fn (TreatmentPlanItemModel $i) => $this->item($i))->all());
    }

    /**
     * @param  list<TreatmentPlanModel>  $plans
     * @return list<TreatmentPlanRecord>
     */
    private function plans(array $plans): array
    {
        if ($plans === []) {
            return [];
        }
        $items = [];
        foreach (TreatmentPlanItemModel::query()->whereIn('plan_id', array_map(fn (TreatmentPlanModel $p) => $p->id, $plans))->orderBy('sequence')->get() as $item) {
            $items[$item->plan_id][] = $this->item($item);
        }

        return array_map(fn (TreatmentPlanModel $p) => new TreatmentPlanRecord(
            $p->id, $p->patient_id, $p->title, PlanStatus::from($p->status), $p->notes, $p->created_at->toDateTimeImmutable(), $items[$p->id] ?? [],
        ), $plans);
    }

    private function item(TreatmentPlanItemModel $i): PlanItemRecord
    {
        return new PlanItemRecord(
            $i->id, $i->plan_id, $i->sequence, $i->procedure_id, $i->procedure_code, $i->procedure_name, $i->tooth_fdi, $i->quantity,
            $i->unit_price_amount, $i->estimate_amount, PlanItemStatus::from($i->status), $i->notes, $i->encounter_id, $i->completed_at?->toDateTimeImmutable(),
        );
    }
}
