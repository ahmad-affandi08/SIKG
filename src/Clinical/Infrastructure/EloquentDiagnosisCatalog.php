<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use Src\Clinical\Application\Diagnosis\DiagnosisCatalog;
use Src\Clinical\Application\Diagnosis\DiagnosisSummary;
use Src\Clinical\Infrastructure\Eloquent\DiagnosisModel;

final class EloquentDiagnosisCatalog implements DiagnosisCatalog
{
    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }
        $like = addcslashes($term, '\\%_');

        return array_values(DiagnosisModel::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('icd10_code', 'like', $like.'%')->orWhere('name', 'like', '%'.$like.'%'))
            ->orderByRaw('CASE WHEN icd10_code LIKE ? THEN 0 ELSE 1 END', [$like.'%'])
            ->orderBy('icd10_code')
            ->limit(min($limit, 20))
            ->get()
            ->map(fn (DiagnosisModel $d) => new DiagnosisSummary($d->id, $d->icd10_code, $d->name))
            ->all());
    }
}
