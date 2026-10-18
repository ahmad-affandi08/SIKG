<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

/**
 * Hasil pratinjau impor: jumlah baris sah, baris yang sudah ada (dilewati), dan galat per baris.
 */
final readonly class MasterImportPreview
{
    /**
     * @param  list<int>  $existingRows  nomor baris yang pengenalnya sudah ada
     * @param  list<array{row: int, messages: list<string>}>  $errors  dibatasi untuk tampilan
     */
    public function __construct(
        public string $token,
        public int $totalRows,
        public int $validRows,
        public array $existingRows,
        public int $errorRows,
        public array $errors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'total_rows' => $this->totalRows,
            'valid_rows' => $this->validRows,
            'existing_rows' => array_slice($this->existingRows, 0, 50),
            'existing_count' => count($this->existingRows),
            'error_rows' => $this->errorRows,
            'errors' => $this->errors,
        ];
    }
}
