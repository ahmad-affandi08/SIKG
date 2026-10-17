<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\Clinic\Contracts\PaymentMethodSnapshot;
use Src\Clinic\Infrastructure\Eloquent\PaymentMethodModel;

final class EloquentPaymentMethodCatalog implements PaymentMethodCatalog
{
    public function find(string $id): ?PaymentMethodSnapshot
    {
        $row = PaymentMethodModel::query()->find($id);

        return $row === null ? null : $this->snapshot($row);
    }

    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $map = [];
        foreach (PaymentMethodModel::query()->whereIn('id', $ids)->get() as $row) {
            $map[$row->id] = $this->snapshot($row);
        }

        return $map;
    }

    public function active(): array
    {
        return array_values(PaymentMethodModel::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (PaymentMethodModel $m) => $this->snapshot($m))->all());
    }

    public function cashMethodIds(): array
    {
        return array_values(PaymentMethodModel::query()->where('type', 'cash')->pluck('id')->all());
    }

    private function snapshot(PaymentMethodModel $m): PaymentMethodSnapshot
    {
        return new PaymentMethodSnapshot($m->id, (string) $m->name, (string) $m->type, (bool) $m->is_active);
    }
}
