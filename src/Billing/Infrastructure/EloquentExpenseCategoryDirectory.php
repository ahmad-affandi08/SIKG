<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use Src\Billing\Domain\ExpenseCategoryDirectory;
use Src\Billing\Infrastructure\Eloquent\ExpenseCategoryModel;

final class EloquentExpenseCategoryDirectory implements ExpenseCategoryDirectory
{
    public function isActive(string $categoryId): bool
    {
        return ExpenseCategoryModel::query()->whereKey($categoryId)->where('is_active', true)->exists();
    }

    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ExpenseCategoryModel::query()->withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
    }

    public function options(): array
    {
        return array_values(ExpenseCategoryModel::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (ExpenseCategoryModel $c) => ['value' => $c->id, 'label' => $c->name])->all());
    }
}
