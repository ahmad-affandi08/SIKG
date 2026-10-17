<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure;

use DateTimeImmutable;
use Src\Reporting\Application\ReportJobStore;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\JobStatus;
use Src\Reporting\Domain\ReportKey;
use Src\Reporting\Domain\ReportScope;
use Src\Reporting\Infrastructure\Eloquent\ReportJobModel;

/**
 * @phpstan-import-type JobRow from ReportJobStore
 */
final class EloquentReportJobStore implements ReportJobStore
{
    public function create(ReportKey $key, ExportFormat $format, ReportScope $scope, array $filters, string $userId, DateTimeImmutable $at): array
    {
        $job = new ReportJobModel;
        $job->forceFill([
            'report_key' => $key->value,
            'format' => $format->value,
            'status' => JobStatus::Queued->value,
            'scope' => $scope->value,
            'filters' => $filters,
            'requested_by' => $userId,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $this->row($job);
    }

    public function find(string $id): ?array
    {
        $job = ReportJobModel::query()->find($id);

        return $job instanceof ReportJobModel ? $this->row($job) : null;
    }

    public function claim(string $id, DateTimeImmutable $at): ?array
    {
        $job = ReportJobModel::query()->find($id);
        if (! $job instanceof ReportJobModel || ! in_array($job->status, [JobStatus::Queued->value, JobStatus::Running->value], true)) {
            return null;
        }
        $job->forceFill(['status' => JobStatus::Running->value, 'started_at' => $at, 'updated_at' => $at])->save();

        return $this->row($job);
    }

    public function finish(string $id, string $path, int $size, DateTimeImmutable $at, DateTimeImmutable $expiresAt): void
    {
        ReportJobModel::query()->whereKey($id)->update([
            'status' => JobStatus::Done->value, 'file_path' => $path, 'file_size' => $size, 'error_message' => null,
            'finished_at' => $at, 'expires_at' => $expiresAt, 'updated_at' => $at,
        ]);
    }

    public function fail(string $id, string $message, DateTimeImmutable $at): void
    {
        ReportJobModel::query()->whereKey($id)->update([
            'status' => JobStatus::Failed->value, 'error_message' => mb_substr($message, 0, 255), 'finished_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function filePath(string $id): ?string
    {
        $job = ReportJobModel::query()->find($id);

        return $job instanceof ReportJobModel ? $job->file_path : null;
    }

    public function expired(DateTimeImmutable $now, int $limit): array
    {
        $rows = ReportJobModel::query()
            ->whereNotNull('file_path')
            ->where('expires_at', '<=', $now)
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        $list = [];
        foreach ($rows as $job) {
            if ($job->file_path !== null) {
                $list[] = ['id' => $job->id, 'path' => $job->file_path];
            }
        }

        return $list;
    }

    public function releaseFile(string $id): void
    {
        ReportJobModel::query()->whereKey($id)->update(['file_path' => null]);
    }

    /**
     * @return JobRow
     */
    private function row(ReportJobModel $job): array
    {
        return [
            'id' => $job->id,
            'report_key' => $job->report_key,
            'format' => $job->format,
            'status' => $job->status,
            'scope' => $job->scope,
            'filters' => $job->filters,
            'requested_by' => $job->requested_by,
            'file_size' => $job->file_size,
            'has_file' => $job->file_path !== null,
            'error' => $job->error_message,
            'created_at' => $job->created_at->toIso8601String(),
            'started_at' => $job->started_at?->toIso8601String(),
            'finished_at' => $job->finished_at?->toIso8601String(),
            'expires_at' => $job->expires_at?->toIso8601String(),
        ];
    }
}
