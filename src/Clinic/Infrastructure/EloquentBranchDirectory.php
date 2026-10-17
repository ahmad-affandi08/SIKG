<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\BranchDirectory;
use Src\Clinic\Contracts\BranchSummary;
use Src\Clinic\Infrastructure\Eloquent\BranchModel;

final class EloquentBranchDirectory implements BranchDirectory
{
    public function all(): array
    {
        return array_values(BranchModel::query()->orderBy('name')->get()
            ->map(fn (BranchModel $b) => new BranchSummary($b->id, $b->name, (bool) $b->getAttribute('is_active')))
            ->all());
    }

    public function exists(string $branchId): bool
    {
        return BranchModel::query()->whereKey($branchId)->exists();
    }
}
