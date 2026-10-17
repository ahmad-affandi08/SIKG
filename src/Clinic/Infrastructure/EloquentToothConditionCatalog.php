<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ToothCondition;
use Src\Clinic\Contracts\ToothConditionCatalog;
use Src\Clinic\Infrastructure\Eloquent\ToothConditionModel;

final class EloquentToothConditionCatalog implements ToothConditionCatalog
{
    public function find(string $code): ?ToothCondition
    {
        $row = ToothConditionModel::query()->where('code', $code)->first();

        return $row === null ? null : $this->condition($row);
    }

    public function all(): array
    {
        return array_values(ToothConditionModel::query()->orderBy('code')->get()->map(fn (ToothConditionModel $r) => $this->condition($r))->all());
    }

    private function condition(ToothConditionModel $r): ToothCondition
    {
        /** @var 'surface'|'tooth'|'both' $scope */
        $scope = $r->scope;

        return new ToothCondition($r->code, $r->name, $r->color, $scope, $r->is_active);
    }
}
