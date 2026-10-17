<?php

declare(strict_types=1);

namespace Src\Patient\Domain\Import;

/**
 * Keadaan satu impor pasien. `nextRow` adalah penunjuk lanjutan: potongan yang sudah diproses tidak diulang sehingga
 * job yang berjalan dua kali tidak menggandakan data. `errors` hanya memuat nomor baris dan pesan, tanpa isi data pasien.
 */
final readonly class ImportBatch
{
    /**
     * @param  list<array{row: int, message: string}>  $errors
     * @param  list<int>  $duplicateRowNumbers  nomor baris berkas Excel
     */
    public function __construct(
        public string $id,
        public string $createdBy,
        public ImportStatus $status,
        public string $filePath,
        public int $totalRows,
        public int $validRows,
        public int $errorRows,
        public int $duplicateRows,
        public int $importedRows,
        public int $skippedRows,
        public int $nextRow,
        public bool $includeDuplicates,
        public array $errors,
        public array $duplicateRowNumbers,
        public ?string $failureReason,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_rows' => $this->totalRows,
            'valid_rows' => $this->validRows,
            'error_rows' => $this->errorRows,
            'duplicate_rows' => $this->duplicateRows,
            'imported_rows' => $this->importedRows,
            'skipped_rows' => $this->skippedRows,
            'include_duplicates' => $this->includeDuplicates,
            'errors' => $this->errors,
            'duplicate_row_numbers' => array_slice($this->duplicateRowNumbers, 0, 200),
            'failure_reason' => $this->failureReason,
        ];
    }
}
