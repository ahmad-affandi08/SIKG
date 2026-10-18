<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use Src\Pharmacy\Contracts\MedicineCatalog;
use Src\Pharmacy\Contracts\MedicineInfo;
use Src\Pharmacy\Infrastructure\Eloquent\MedicineModel;
use Src\Pharmacy\Infrastructure\Eloquent\StockBalanceModel;

final class EloquentMedicineCatalog implements MedicineCatalog
{
    public function find(string $medicineId): ?MedicineInfo
    {
        $m = MedicineModel::query()->find($medicineId);

        return $m === null ? null : new MedicineInfo($m->id, $m->name, $m->unit, $m->sell_price_amount, $m->drug_class, $m->is_active);
    }

    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = addcslashes($term, '\\%_');
        $medicines = MedicineModel::query()->where('is_active', true)->where('name', 'like', '%'.$like.'%')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$like.'%'])->orderBy('name')->limit(min($limit, 20))->get();
        $balances = StockBalanceModel::totals(array_values($medicines->pluck('id')->map(fn ($id) => (string) $id)->all()));

        return array_values($medicines->map(fn (MedicineModel $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'unit' => $m->unit,
            'sell_price_amount' => $m->sell_price_amount,
            'drug_class' => $m->drug_class,
            'available' => (int) ($balances[$m->id] ?? 0),
        ])->all());
    }
}
