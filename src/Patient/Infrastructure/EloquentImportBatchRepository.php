<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use Src\Patient\Domain\Import\ImportBatch;
use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Patient\Domain\Import\ImportStatus;
use Src\Patient\Infrastructure\Eloquent\ImportBatchModel;
use Src\Shared\Application\Clock;

final readonly class EloquentImportBatchRepository implements ImportBatchRepository
{
    public function __construct(private Clock $clock) {}

    public function create(string $actorId, string $filePath): string
    {
        $batch = new ImportBatchModel;
        $batch->forceFill(['type' => 'patients', 'file_path' => $filePath, 'status' => ImportStatus::Previewed->value, 'created_by' => $actorId])->save();

        return $batch->id;
    }

    public function find(string $id): ?ImportBatch
    {
        $model = ImportBatchModel::query()->where('type', 'patients')->find($id);

        return $model === null ? null : $this->toBatch($model);
    }

    public function findForUpdate(string $id): ?ImportBatch
    {
        $model = ImportBatchModel::query()->where('type', 'patients')->lockForUpdate()->find($id);

        return $model === null ? null : $this->toBatch($model);
    }

    public function markPreviewed(string $id, int $total, int $valid, int $errorRows, array $errors, array $duplicateRows): void
    {
        ImportBatchModel::query()->whereKey($id)->update([
            'status' => ImportStatus::Previewed->value,
            'total_rows' => $total,
            'valid_rows' => $valid,
            'error_rows' => $errorRows,
            'duplicate_rows' => count($duplicateRows),
            'errors' => json_encode($errors, JSON_THROW_ON_ERROR),
            'duplicate_row_numbers' => json_encode($duplicateRows, JSON_THROW_ON_ERROR),
            'updated_at' => $this->clock->now(),
        ]);
    }

    public function markImporting(string $id, bool $includeDuplicates): void
    {
        ImportBatchModel::query()->whereKey($id)->update([
            'status' => ImportStatus::Importing->value,
            'include_duplicates' => $includeDuplicates,
            'next_row' => 0,
            'imported_rows' => 0,
            'skipped_rows' => 0,
            'updated_at' => $this->clock->now(),
        ]);
    }

    public function advance(string $id, int $nextRow, int $imported, int $skipped): void
    {
        ImportBatchModel::query()->whereKey($id)->update(['next_row' => $nextRow, 'imported_rows' => $imported, 'skipped_rows' => $skipped, 'updated_at' => $this->clock->now()]);
    }

    public function markCompleted(string $id): void
    {
        ImportBatchModel::query()->whereKey($id)->update(['status' => ImportStatus::Completed->value, 'completed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
    }

    public function markFailed(string $id, string $reason): void
    {
        ImportBatchModel::query()->whereKey($id)->update(['status' => ImportStatus::Failed->value, 'failure_reason' => mb_substr($reason, 0, 250), 'updated_at' => $this->clock->now()]);
    }

    private function toBatch(ImportBatchModel $m): ImportBatch
    {
        return new ImportBatch(
            id: $m->id,
            createdBy: (string) $m->created_by,
            status: ImportStatus::from($m->status),
            filePath: $m->file_path,
            totalRows: $m->total_rows,
            validRows: $m->valid_rows,
            errorRows: $m->error_rows,
            duplicateRows: $m->duplicate_rows,
            importedRows: $m->imported_rows,
            skippedRows: $m->skipped_rows,
            nextRow: $m->next_row,
            includeDuplicates: $m->include_duplicates,
            errors: $m->errors ?? [],
            duplicateRowNumbers: $m->duplicate_row_numbers ?? [],
            failureReason: $m->failure_reason,
        );
    }
}
