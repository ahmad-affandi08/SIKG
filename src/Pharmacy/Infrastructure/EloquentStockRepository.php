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

    public function apply(string $actorId, string $medicineId, MovementType $type, int $delta, ?string $referenceType, ?string $referenceId, ?string $reason, ?string $batchNo, ?string $expiryDate, DateTimeImmutable $at): int
    {
        $balance = StockBalanceModel::query()->where('medicine_id', $medicineId)->lockForUpdate()->firstOrFail();
        $after = $balance->quantity + $delta;
        if ($after < 0) {
            throw new LogicException('Saldo stok tidak boleh negatif.');
        }
        $balance->forceFill(['quantity' => $after])->save();

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
}
