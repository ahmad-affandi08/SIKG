<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Src\Pharmacy\Domain\InsufficientStock;
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

    /**
     * Cabang pemilik stok untuk permintaan ini: cabang aktif, atau gudang pusat (null) bila tidak terikat cabang (P3-04).
     */
    private function bucket(): ?string
    {
        return $this->tenant->branchId();
    }

    /**
     * @return Builder<StockBalanceModel>
     */
    private function balanceRow(string $medicineId): Builder
    {
        $query = StockBalanceModel::query()->where('medicine_id', $medicineId);
        $bucket = $this->bucket();

        return $bucket === null ? $query->whereNull('branch_id') : $query->where('branch_id', $bucket);
    }

    public function lockedBalance(string $medicineId): int
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        // insertOrIgnore aman terhadap dua proses yang membuat baris saldo bersamaan (unik per obat dan cabang).
        DB::table('stock_balances')->insertOrIgnore([
            'id' => $this->ids->generate(),
            'clinic_id' => $clinic,
            'branch_id' => $this->bucket(),
            'medicine_id' => $medicineId,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->balanceRow($medicineId)->lockForUpdate()->firstOrFail()->quantity;
    }

    public function apply(string $actorId, string $medicineId, MovementType $type, int $delta, ?string $referenceType, ?string $referenceId, ?string $reason, ?string $batchNo, ?string $expiryDate, DateTimeImmutable $at, ?int $unitCostAmount = null, ?string $supplierId = null, ?string $purchaseOrderId = null): int
    {
        $balance = $this->balanceRow($medicineId)->lockForUpdate()->firstOrFail();
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
        $balances = StockBalanceModel::totals();

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
        $bucket = $this->bucket();
        $batches = StockBatchModel::query()->where('medicine_id', $medicineId)->where('remaining_quantity', '>', 0)
            ->when($bucket === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $bucket))
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

    public function transfer(string $actorId, string $transferId, string $medicineId, int $quantity, ?string $fromBranch, ?string $toBranch, ?string $reason, DateTimeImmutable $at): array
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        $zero = '00000000000000000000000000';
        foreach ([$fromBranch, $toBranch] as $branch) {
            DB::table('stock_balances')->insertOrIgnore([
                'id' => $this->ids->generate(), 'clinic_id' => $clinic, 'branch_id' => $branch, 'medicine_id' => $medicineId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Kedua saldo dikunci menurut urutan kunci cabang yang sama agar dua transfer berlawanan tidak saling menunggu (deadlock).
        $rows = DB::table('stock_balances')->where('clinic_id', $clinic)->where('medicine_id', $medicineId)
            ->whereIn('branch_key', [$fromBranch ?? $zero, $toBranch ?? $zero])->orderBy('branch_key')->lockForUpdate()->get(['id', 'branch_key', 'quantity'])->keyBy('branch_key');
        $from = $rows->get($fromBranch ?? $zero) ?? throw new RuntimeException('Saldo asal tidak ditemukan.');
        $to = $rows->get($toBranch ?? $zero) ?? throw new RuntimeException('Saldo tujuan tidak ditemukan.');
        if ((int) $from->quantity < $quantity) {
            throw new InsufficientStock((int) $from->quantity, $quantity);
        }
        $fromAfter = (int) $from->quantity - $quantity;
        $toAfter = (int) $to->quantity + $quantity;
        DB::table('stock_balances')->where('id', $from->id)->update(['quantity' => $fromAfter, 'updated_at' => now()]);
        DB::table('stock_balances')->where('id', $to->id)->update(['quantity' => $toAfter, 'updated_at' => now()]);

        DB::table('stock_transfers')->insert([
            'id' => $transferId, 'clinic_id' => $clinic, 'branch_id' => $fromBranch, 'from_branch_id' => $fromBranch, 'to_branch_id' => $toBranch, 'medicine_id' => $medicineId,
            'quantity' => $quantity, 'reason' => $reason, 'created_by' => $actorId, 'created_at' => $at,
        ]);

        // FEFO di cabang asal; batch dibuat ulang di tujuan dengan identitas yang sama. Selisih tanpa batch (saldo lama) menjadi satu batch polos.
        $source = DB::table('stock_batches')->where('clinic_id', $clinic)->where('medicine_id', $medicineId)->where('remaining_quantity', '>', 0)->whereNull('deleted_at')
            ->when($fromBranch === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $fromBranch))
            ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('received_at')->orderBy('id')->lockForUpdate()->get();
        $left = $quantity;
        $slices = [];
        foreach ($source as $batch) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (int) $batch->remaining_quantity);
            DB::table('stock_batches')->where('id', $batch->id)->update(['remaining_quantity' => (int) $batch->remaining_quantity - $take, 'updated_at' => now()]);
            $slices[] = ['batch_no' => $batch->batch_no, 'expiry_date' => $batch->expiry_date, 'unit_cost_amount' => $batch->unit_cost_amount, 'supplier_id' => $batch->supplier_id, 'purchase_order_id' => $batch->purchase_order_id, 'quantity' => $take];
            $left -= $take;
        }
        if ($left > 0) {
            $slices[] = ['batch_no' => null, 'expiry_date' => null, 'unit_cost_amount' => null, 'supplier_id' => null, 'purchase_order_id' => null, 'quantity' => $left];
        }
        foreach ($slices as $slice) {
            DB::table('stock_batches')->insert([
                'id' => $this->ids->generate(), 'clinic_id' => $clinic, 'branch_id' => $toBranch, 'medicine_id' => $medicineId, 'batch_no' => $slice['batch_no'], 'expiry_date' => $slice['expiry_date'],
                'received_quantity' => $slice['quantity'], 'remaining_quantity' => $slice['quantity'], 'unit_cost_amount' => $slice['unit_cost_amount'], 'supplier_id' => $slice['supplier_id'],
                'purchase_order_id' => $slice['purchase_order_id'], 'transfer_id' => $transferId, 'received_at' => $at, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ([[$fromBranch, MovementType::Out, -$quantity, $fromAfter], [$toBranch, MovementType::In, $quantity, $toAfter]] as [$branch, $type, $delta, $after]) {
            DB::table('stock_movements')->insert([
                'id' => $this->ids->generate(), 'clinic_id' => $clinic, 'branch_id' => $branch, 'medicine_id' => $medicineId, 'type' => $type->value, 'quantity' => $delta, 'balance_after' => $after,
                'reference_type' => 'stock_transfer', 'reference_id' => $transferId, 'reason' => $reason, 'created_by' => $actorId, 'created_at' => $at,
            ]);
        }

        return ['from_after' => $fromAfter, 'to_after' => $toAfter];
    }

    public function branchBalances(): array
    {
        $rows = StockBalanceModel::query()->orderBy('branch_id')->get();
        $medicines = MedicineModel::query()->whereIn('id', $rows->pluck('medicine_id')->unique()->all())->get()->keyBy('id');
        $list = [];
        foreach ($rows as $row) {
            $medicine = $medicines->get($row->medicine_id);
            if ($medicine === null || ($row->quantity < 1 && ! $medicine->is_active)) {
                continue;
            }
            $list[] = ['medicine_id' => $row->medicine_id, 'name' => $medicine->name, 'unit' => $medicine->unit, 'branch_id' => $row->branch_id, 'quantity' => $row->quantity];
        }
        usort($list, fn (array $a, array $b): int => strcmp($a['name'].'|'.($a['branch_id'] ?? ''), $b['name'].'|'.($b['branch_id'] ?? '')));

        return $list;
    }

    public function transfers(int $limit): array
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        $bucket = $this->bucket();
        $rows = DB::table('stock_transfers as t')->join('medicines as m', 'm.id', '=', 't.medicine_id')->where('t.clinic_id', $clinic)
            ->when($bucket !== null, fn ($q) => $q->where(fn ($w) => $w->where('t.from_branch_id', $bucket)->orWhere('t.to_branch_id', $bucket)))
            ->orderByDesc('t.created_at')->orderByDesc('t.id')->limit($limit)
            ->get(['t.id', 't.medicine_id', 'm.name', 'm.unit', 't.from_branch_id', 't.to_branch_id', 't.quantity', 't.reason', 't.created_by', 't.created_at']);

        return array_values($rows->map(fn ($r) => [
            'id' => (string) $r->id, 'medicine_id' => (string) $r->medicine_id, 'name' => (string) $r->name, 'unit' => (string) $r->unit, 'from_branch_id' => $r->from_branch_id, 'to_branch_id' => $r->to_branch_id,
            'quantity' => (int) $r->quantity, 'reason' => $r->reason, 'created_by' => $r->created_by, 'created_at' => new DateTimeImmutable((string) $r->created_at),
        ])->all());
    }
}
