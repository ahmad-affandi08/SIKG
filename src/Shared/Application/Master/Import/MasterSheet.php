<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

use Src\Shared\Application\Master\ImportableMaster;

/**
 * Port berkas lembar kerja untuk impor master data.
 */
interface MasterSheet
{
    /** Templat .xlsx: baris judul sesuai kolom formulir dan lembar petunjuk. */
    public function template(ImportableMaster $entity): string;

    /**
     * Membaca berkas (.xlsx atau .csv) menjadi baris bernilai teks, berkunci kunci kolom entitas. Baris kosong dilewati.
     * Tanggal pada kolom bertipe date sudah dibuat menjadi YYYY-MM-DD.
     *
     * @return list<array{row: int, values: array<string, string>}>
     *
     * @throws MasterImportInvalid
     */
    public function read(ImportableMaster $entity, string $contents): array;
}
