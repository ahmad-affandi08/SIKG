<?php

declare(strict_types=1);

namespace Src\Lab\Infrastructure;

use Src\Lab\Domain\LaboratoryDirectory;
use Src\Lab\Infrastructure\Eloquent\LaboratoryModel;

final class EloquentLaboratoryDirectory implements LaboratoryDirectory
{
    public function isActive(string $laboratoryId): bool
    {
        return LaboratoryModel::query()->whereKey($laboratoryId)->where('is_active', true)->exists();
    }

    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return LaboratoryModel::query()->withTrashed()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($name) => (string) $name)->all();
    }

    public function options(): array
    {
        return array_values(LaboratoryModel::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (LaboratoryModel $l) => ['value' => $l->id, 'label' => $l->name])->all());
    }
}
