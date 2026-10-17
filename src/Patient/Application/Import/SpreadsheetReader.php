<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

/**
 * Pembaca berkas Excel impor pasien yang berpotongan agar seluruh berkas tidak dimuat ke memori (aturan hosting M21).
 */
interface SpreadsheetReader
{
    /**
     * Jumlah baris data (di luar baris judul).
     *
     * @throws ImportFileInvalid bila berkas tidak terbaca atau kolom wajib tidak ada
     */
    public function countRows(string $localPath): int;

    /**
     * @param  int  $offset  jumlah baris data yang dilewati
     * @return array<int, array<string, scalar|null>> nomor baris berkas => nilai per kolom
     */
    public function read(string $localPath, int $offset, int $limit): array;

    /**
     * Isi berkas templat .xlsx beserta satu baris contoh fiktif.
     */
    public function template(): string;
}
