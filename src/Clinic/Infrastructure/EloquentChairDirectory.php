<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ChairDirectory;
use Src\Clinic\Contracts\ChairSummary;
use Src\Clinic\Infrastructure\Eloquent\ChairModel;

final class EloquentChairDirectory implements ChairDirectory
{
    public function find(string $chairId): ?ChairSummary
    {
        $c = ChairModel::query()->find($chairId);

        return $c === null ? null : new ChairSummary($c->id, $c->name, $c->is_active);
    }

    public function active(): array
    {
        return array_values(ChairModel::query()->where('is_active', true)->orderBy('name')->get()->map(fn (ChairModel $c) => new ChairSummary($c->id, $c->name, $c->is_active))->all());
    }

    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (ChairModel::withTrashed()->whereKey(array_values(array_unique($ids)))->get() as $c) {
            $out[$c->id] = new ChairSummary($c->id, $c->name, $c->is_active);
        }

        return $out;
    }
}
