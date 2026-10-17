<?php

declare(strict_types=1);

namespace Src\Patient\Domain\Import;

interface ImportBatchRepository
{
    public function create(string $actorId, string $filePath): string;

    public function find(string $id): ?ImportBatch;

    /**
     * Mengunci baris batch selama transaksi pemanggil agar dua job tidak memproses potongan yang sama.
     */
    public function findForUpdate(string $id): ?ImportBatch;

    /**
     * @param  list<array{row: int, message: string}>  $errors
     * @param  list<int>  $duplicateRows
     */
    public function markPreviewed(string $id, int $total, int $valid, int $errorRows, array $errors, array $duplicateRows): void;

    public function markImporting(string $id, bool $includeDuplicates): void;

    public function advance(string $id, int $nextRow, int $imported, int $skipped): void;

    public function markCompleted(string $id): void;

    public function markFailed(string $id, string $reason): void;
}
