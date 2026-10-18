<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use DateTimeImmutable;
use Src\Pharmacy\Domain\StockCountLine;
use Src\Pharmacy\Domain\StockCountRepository;
use Src\Pharmacy\Domain\StockCountSession;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockBalanceModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockCountLineModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockCountScheduleModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockCountSessionModel;

final class EloquentStockCountRepository implements StockCountRepository
{
    public function schedule(): array
    {
        $m = StockCountScheduleModel::query()->first();

        return ['enabled' => $m->enabled ?? false, 'interval_days' => $m->interval_days ?? 30, 'next_due_date' => $m?->next_due_date?->toDateString()];
    }

    public function saveSchedule(?string $actorId, bool $enabled, int $intervalDays, ?string $nextDueDate): void
    {
        $m = StockCountScheduleModel::query()->first() ?? new StockCountScheduleModel;
        $m->forceFill(['enabled' => $enabled, 'interval_days' => $intervalDays, 'next_due_date' => $nextDueDate, 'updated_by' => $actorId])->save();
    }

    public function openSession(): ?StockCountSession
    {
        $m = StockCountSessionModel::query()->where('status', 'open')->orderBy('scheduled_for')->first();

        return $m === null ? null : $this->session($m);
    }

    public function createSession(?string $actorId, string $scheduledFor, string $source, ?string $note): StockCountSession
    {
        $m = new StockCountSessionModel;
        $m->forceFill(['scheduled_for' => $scheduledFor, 'status' => 'open', 'source' => $source, 'note' => $note, 'created_by' => $actorId])->save();

        $balances = StockBalanceModel::totals();
        foreach (MedicineModel::query()->where('is_active', true)->orderBy('name')->get() as $medicine) {
            (new StockCountLineModel)->forceFill(['session_id' => $m->id, 'medicine_id' => $medicine->id, 'system_quantity' => (int) ($balances[$medicine->id] ?? 0)])->save();
        }

        return $this->session($m);
    }

    public function lockSession(string $id): ?StockCountSession
    {
        $m = StockCountSessionModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->session($m);
    }

    public function findSession(string $id): ?StockCountSession
    {
        $m = StockCountSessionModel::query()->find($id);

        return $m === null ? null : $this->session($m);
    }

    public function recentSessions(int $limit): array
    {
        return array_values(StockCountSessionModel::query()->orderByDesc('scheduled_for')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (StockCountSessionModel $m) => $this->session($m))->all());
    }

    public function lines(string $sessionId): array
    {
        $medicines = MedicineModel::query()->withTrashed()->pluck('name', 'id');

        return array_values(StockCountLineModel::query()->where('session_id', $sessionId)->get()
            ->sortBy(fn (StockCountLineModel $l) => mb_strtolower((string) ($medicines[$l->medicine_id] ?? '')))
            ->map(fn (StockCountLineModel $l) => new StockCountLine($l->id, $l->session_id, $l->medicine_id, $l->system_quantity, $l->counted_quantity))->all());
    }

    public function recordCount(string $sessionId, string $lineId, ?int $counted): void
    {
        StockCountLineModel::query()->where('session_id', $sessionId)->whereKey($lineId)->update(['counted_quantity' => $counted]);
    }

    public function closeSession(string $actorId, string $id, string $status, DateTimeImmutable $at): void
    {
        StockCountSessionModel::query()->whereKey($id)->update(['status' => $status, 'completed_by' => $actorId, 'completed_at' => $at]);
    }

    private function session(StockCountSessionModel $m): StockCountSession
    {
        return new StockCountSession($m->id, $m->scheduled_for->toDateString(), $m->status, $m->source, $m->note);
    }
}
