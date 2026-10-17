<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Semua teks ditulis sebagai string eksplisit. Tanpa ini, nama pasien atau catatan yang diawali "=" dievaluasi Excel sebagai
 * rumus saat laporan dibuka (formula injection). Angka dan tanggal tetap diperlakukan seperti biasa.
 */
trait WritesTextAsStrings
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }
}
