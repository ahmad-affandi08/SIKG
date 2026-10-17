<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure;

use Src\Insurance\Domain\InsurerDirectory;
use Src\Insurance\Domain\InsurerRecord;
use Src\Insurance\Infrastructure\Eloquent\InsurerModel;

final class EloquentInsurerDirectory implements InsurerDirectory
{
    public function find(string $id): ?InsurerRecord
    {
        $m = InsurerModel::query()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (InsurerModel::withTrashed()->whereKey(array_values(array_unique($ids)))->get() as $m) {
            $out[$m->id] = $this->record($m);
        }

        return $out;
    }

    public function active(): array
    {
        return array_values(InsurerModel::query()->where('is_active', true)->orderBy('name')->get()->map(fn (InsurerModel $m) => $this->record($m))->all());
    }

    private function record(InsurerModel $m): InsurerRecord
    {
        return new InsurerRecord($m->id, $m->name, $m->type, $m->is_active);
    }
}
