<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use Src\Pharmacy\Domain\SupplierDirectory;
use Src\Pharmacy\Infrastructure\Eloquent\SupplierModel;

final class EloquentSupplierDirectory implements SupplierDirectory
{
    public function isActive(string $supplierId): bool
    {
        return SupplierModel::query()->whereKey($supplierId)->where('is_active', true)->exists();
    }

    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return SupplierModel::query()->withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
    }

    public function options(): array
    {
        return array_values(SupplierModel::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (SupplierModel $s) => ['value' => $s->id, 'label' => $s->name])->all());
    }
}
