<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Backup;

use DateTimeImmutable;
use Src\Shared\Application\Backup\BackupRunLog;

final class EloquentBackupRunLog implements BackupRunLog
{
    public function record(string $kind, bool $ok, ?string $file, string $message, array $details, DateTimeImmutable $startedAt, DateTimeImmutable $finishedAt): void
    {
        $run = new BackupRunModel;
        $run->forceFill([
            'kind' => $kind,
            'ok' => $ok,
            'file' => $file,
            'message' => mb_substr($message, 0, 500),
            'details' => $details === [] ? null : $details,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ])->save();
    }

    public function latest(string $kind, bool $onlyOk = false): ?array
    {
        $query = BackupRunModel::query()->where('kind', $kind);
        if ($onlyOk) {
            $query->where('ok', true);
        }
        $run = $query->orderByDesc('finished_at')->orderByDesc('id')->first();
        if (! $run instanceof BackupRunModel) {
            return null;
        }

        return ['ok' => $run->ok, 'file' => $run->file, 'message' => $run->message, 'details' => $run->details ?? [], 'finished_at' => DateTimeImmutable::createFromInterface($run->finished_at)];
    }

    public function recent(int $limit): array
    {
        $list = [];
        foreach (BackupRunModel::query()->orderByDesc('finished_at')->orderByDesc('id')->limit($limit)->get() as $run) {
            if ($run instanceof BackupRunModel) {
                $list[] = ['kind' => $run->kind, 'ok' => $run->ok, 'file' => $run->file, 'message' => $run->message, 'finished_at' => $run->finished_at->format('c')];
            }
        }

        return $list;
    }
}
