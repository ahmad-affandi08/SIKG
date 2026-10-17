<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinic\Contracts\ProcedureSnapshot;
use Src\Clinic\Infrastructure\Eloquent\ProcedureModel;

final class EloquentProcedureCatalog implements ProcedureCatalog
{
    public function find(string $procedureId): ?ProcedureSnapshot
    {
        $procedure = ProcedureModel::query()->where('is_active', true)->find($procedureId);

        return $procedure === null ? null : $this->snapshot($procedure);
    }

    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = addcslashes($term, '\\%_');

        return array_values(ProcedureModel::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('code', 'like', $like.'%')->orWhere('name', 'like', '%'.$like.'%'))
            ->orderByRaw('CASE WHEN code LIKE ? THEN 0 ELSE 1 END', [$like.'%'])
            ->orderBy('code')
            ->limit(min($limit, 20))
            ->get()
            ->map(fn (ProcedureModel $p) => $this->snapshot($p))
            ->all());
    }

    private function snapshot(ProcedureModel $p): ProcedureSnapshot
    {
        return new ProcedureSnapshot($p->id, $p->code, $p->name, $p->scope, (int) $p->default_price_amount, $p->result_condition_code);
    }
}
