<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Src\Pharmacy\Domain\MovementType;
use Src\Pharmacy\Domain\StockMovementRecord;
use Src\Pharmacy\Domain\StockRepository;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockBalanceModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockBatchModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockMovementModel;
use Src\Shared\Application\TenantContext;
use Src\Shared\Domain\IdGenerator;

final readonly class EloquentStockRepository implements StockRepository
{
    public function __construct(private TenantContext $tenant, private IdGenerator $ids) {}

    public function lockedBalance(string $medicineId): int
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        // insertOrIgnore aman terhadap dua proses yang membuat baris saldo bersamaan (unik per obat).
        DB::table('stock_balances')->insertOrIgnore([
            'id' => $this->ids->generate(),
            'clinic_id' => $clinic,
            'medicine_id' => $medicineId,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockBalanceModel::query()->where('medicine_id', $medicineId)->lockForUpdate()->firstOrFail()->quantity;
    }

    public function apply(string $actorId, string $medicineId, MovementType $type, int $delta, ?string $referenceType, ?string $referenceId, ?string $reason, ?string $batchNo, ?string $expiryDate, DateTimeImmutable $at, ?int $unitCostAmount = null, ?string $supplierId = null, ?string $purchaseOrderId = null): int
    {
        $balance = StockBalanceModel::query()->where('medicine_id', $medicineId)->lockForUpdate()->firstOrFail();
        $after = $balance->quantity + $delta;
        if ($after < 0) {
            throw new LogicException('Saldo stok tidak boleh negatif.');
        }
        $balance->forceFill(['quantity' => $after])->save();

        if ($delta > 0) {
            (new StockBatchModel)->forceFill([
                'medicine_id' => $medicineId,
                'batch_no' => $batchNo,
                'expiry_date' => $expiryDate,
                'received_quantity' => $delta,
                'remaining_quantity' => $delta,
                'unit_cost_amount' => $unitCostAmount,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $purchaseOrderId,
                'received_at' => $at,
            ])->save();
        } elseif ($delta < 0) {
            $this->consume($medicineId, -$delta);
        }

        (new StockMovementModel)->forceFill([
            'medicine_id' => $medicineId,
            'type' => $type->value,
            'quantity' => $delta,
            'balance_after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'batch_no' => $batchNo,
            'expiry_date' => $expiryDate,
            'created_by' => $actorId,
            'created_at' => $at,
        ])->save();

        return $after;
    }

    public function movements(string $medicineId, int $limit): array
    {
        return array_values(StockMovementModel::query()->where('medicine_id', $medicineId)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (StockMovementModel $m) => new StockMovementRecord(
                $m->id,
                $m->medicine_id,
                MovementType::from($m->type),
                $m->quantity,
                $m->balance_after,
                $m->reference_type,
                $m->reason,
                $m->batch_no,
                $m->expiry_date?->toDateString(),
                (string) $m->created_by,
                $m->created_at->toDateTimeImmutable(),
            ))->all());
    }

    public function overview(): array
    {
        $balances = StockBalanceModel::query()->pluck('quantity', 'medicine_id');

        return array_values(MedicineModel::query()->orderBy('name')->get()->map(fn (MedicineModel $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'unit' => $m->unit,
            'quantity' => (int) ($balances[$m->id] ?? 0),
            'min_stock' => $m->min_stock,
            'is_active' => $m->is_active,
        ])->all());
    }

    public function netIssued(string $medicineId, string $referenceType, string $referenceId): int
    {
        $sum = (int) StockMovementModel::query()
            ->where('medicine_id', $medicineId)->where('reference_type', $referenceType)->where('reference_id', $referenceId)
            ->sum('quantity');

        return max(0, -$sum);
    }

    public function referencedMedicines(string $referenceType, string $referenceId): array
    {
        return array_values(StockMovementModel::query()->where('reference_type', $referenceType)->where('reference_id', $referenceId)
            ->distinct()->pluck('medicine_id')->map(fn ($id) => (string) $id)->all());
    }

    public function batches(?string $medicineId): array
    {
        $rows = StockBatchModel::query()
            ->where('remaining_quantity', '>', 0)
            ->when($medicineId !== null, fn ($q) => $q->where('medicine_id', $medicineId))
            ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('received_at')->orderBy('id')
            ->get();
        $medicines = MedicineModel::query()->whereIn('id', $rows->pluck('medicine_id')->unique()->all())->get()->keyBy('id');

        $list = [];
        foreach ($rows as $batch) {
            $medicine = $medicines->get($batch->medicine_id);
            if ($medicine === null) {
                continue;
            }
            $list[] = [
                'id' => $batch->id,
                'medicine_id' => $batch->medicine_id,
                'name' => $medicine->name,
                'unit' => $medicine->unit,
                'batch_no' => $batch->batch_no,
                'expiry_date' => $batch->expiry_date?->toDateString(),
                'received_quantity' => $batch->received_quantity,
                'remaining_quantity' => $batch->remaining_quantity,
                'unit_cost_amount' => $batch->unit_cost_amount,
                'supplier_id' => $batch->supplier_id,
                'received_at' => $batch->received_at->toDateString(),
            ];
        }

        return $list;
    }

    public function expiringBatches(string $until): array
    {
        $list = [];
        foreach ($this->batches(null) as $batch) {
            if ($batch['expiry_date'] === null || $batch['expiry_date'] > $until) {
                continue;
            }
            $list[] = [
                'medicine_id' => $batch['medicine_id'],
                'name' => $batch['name'],
                'unit' => $batch['unit'],
                'batch_no' => $batch['batch_no'],
                'expiry_date' => $batch['expiry_date'],
                'received' => $batch['received_quantity'],
                'quantity' => $batch['remaining_quantity'],
            ];
        }

        return $list;
    }

    /**
     * FEFO: batch yang paling cepat kedaluwarsa dipakai lebih dulu, batch tanpa tanggal terakhir. Bila jumlah batch kurang dari kebutuhan
     * (saldo lama sebelum pelacakan batch), selisihnya dibiarkan.
     */
    private function consume(string $medicineId, int $quantity): void
    {
        $batches = StockBatchModel::query()->where('medicine_id', $medicineId)->where('remaining_quantity', '>', 0)
            ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('received_at')->orderBy('id')
            ->lockForUpdate()->get();
        foreach ($batches as $batch) {
            if ($quantity <= 0) {
                break;
            }
            $take = min($quantity, $batch->remaining_quantity);
            $batch->forceFill(['remaining_quantity' => $batch->remaining_quantity - $take])->save();
            $quantity -= $take;
        }
    }
}
