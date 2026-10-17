<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Src\Shared\Application\AuditTrailReader;

final class EloquentAuditTrailReader implements AuditTrailReader
{
    public function entries(array $filters, int $page, int $perPage): array
    {
        $query = AuditLogModel::query();
        if (($filters['action'] ?? null) !== null && trim((string) $filters['action']) !== '') {
            $query->where('action', 'like', addcslashes(trim((string) $filters['action']), '\\%_').'%');
        }
        foreach (['subject_type', 'subject_id', 'actor_id'] as $column) {
            if (($filters[$column] ?? null) !== null && $filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }
        $this->range($query, $filters, 'created_at');

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $perPage)->get();

        $list = [];
        foreach ($rows as $l) {
            if ($l instanceof AuditLogModel) {
                $list[] = [
                    'id' => $l->id,
                    'actor_id' => $l->actor_id,
                    'action' => $l->action,
                    'subject_type' => $l->subject_type,
                    'subject_id' => $l->subject_id,
                    'ip' => $l->ip,
                    'created_at' => $l->created_at->format('c'),
                ];
            }
        }

        return [
            'rows' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function entry(string $id): ?array
    {
        $l = AuditLogModel::query()->find($id);
        if (! $l instanceof AuditLogModel) {
            return null;
        }

        return [
            'id' => $l->id,
            'actor_id' => $l->actor_id,
            'action' => $l->action,
            'subject_type' => $l->subject_type,
            'subject_id' => $l->subject_id,
            'before' => $l->before,
            'after' => $l->after,
            'ip' => $l->ip,
            'user_agent' => $l->user_agent,
            'created_at' => $l->created_at->format('c'),
        ];
    }

    public function subjectTypes(): array
    {
        return array_values(AuditLogModel::query()->distinct()->orderBy('subject_type')->pluck('subject_type')->all());
    }

    public function actorIds(): array
    {
        return array_values(AuditLogModel::query()->whereNotNull('actor_id')->distinct()->pluck('actor_id')->all());
    }

    public function accesses(array $filters, int $page, int $perPage): array
    {
        $query = AccessLogModel::query();
        foreach (['actor_id', 'patient_id'] as $column) {
            if (($filters[$column] ?? null) !== null && $filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }
        $this->range($query, $filters, 'at');

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('at')->orderByDesc('id')->forPage($page, $perPage)->get();

        $list = [];
        foreach ($rows as $a) {
            if ($a instanceof AccessLogModel) {
                $list[] = [
                    'id' => $a->id,
                    'actor_id' => $a->actor_id,
                    'patient_id' => $a->patient_id,
                    'route' => $a->route,
                    'ip' => $a->ip,
                    'at' => $a->at->format('c'),
                ];
            }
        }

        return [
            'rows' => $list,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function range(ImmutableLogBuilder $query, array $filters, string $column): void
    {
        if (($filters['from'] ?? null) !== null) {
            $query->where($column, '>=', $filters['from']);
        }
        if (($filters['to'] ?? null) !== null) {
            $query->where($column, '<', $filters['to']);
        }
    }
}
