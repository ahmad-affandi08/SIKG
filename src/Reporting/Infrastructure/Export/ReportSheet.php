<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportTable;

/**
 * Satu lembar Excel untuk satu tabel laporan. Uang tetap bilangan bulat rupiah dengan format sel; persen disimpan
 * sebagai pecahan agar format persen Excel benar; tanggal sebagai tanggal Excel.
 */
final class ReportSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    /** Baris judul tabel + baris kosong sebelum tajuk kolom. */
    private const HEADER_ROW = 3;

    public function __construct(private readonly string $sheetTitle, private readonly ReportTable $table) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $rows = [[$this->table->title], [], array_map(fn ($c) => $c->label, $this->table->columns)];
        foreach ($this->table->rows as $row) {
            $rows[] = $this->cells($row);
        }
        if ($this->table->totals !== null) {
            $rows[] = $this->cells($this->table->totals);
        }
        if ($this->table->truncated) {
            $rows[] = ['Tabel dipotong pada '.ReportTable::MAX_ROWS.' baris pertama. Persempit rentang tanggal untuk melihat sisanya.'];
        }
        if ($this->table->rows === [] && $this->table->emptyText !== null) {
            $rows[] = [$this->table->emptyText];
        }

        return $rows;
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->table->columns as $index => $column) {
            $letter = self::letter($index);
            $format = match ($column->kind) {
                ColumnKind::Money => '"Rp" #,##0;[Red]-"Rp" #,##0',
                ColumnKind::Integer => '#,##0',
                ColumnKind::Percent => NumberFormat::FORMAT_PERCENTAGE_00,
                ColumnKind::Minutes => '#,##0 "mnt"',
                ColumnKind::Date => 'dd/mm/yyyy',
                ColumnKind::DateTime => 'dd/mm/yyyy hh:mm',
                ColumnKind::Text => null,
            };
            if ($format !== null) {
                $formats[$letter] = $format;
            }
        }

        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => ['font' => ['bold' => true, 'size' => 13]],
            self::HEADER_ROW => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D9E2F3']]],
        ];
        if ($this->table->totals !== null) {
            $styles[self::HEADER_ROW + count($this->table->rows) + 1] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    /**
     * @param  array<string, int|string|null>  $row
     * @return list<int|float|string|null>
     */
    private function cells(array $row): array
    {
        $cells = [];
        foreach ($this->table->columns as $column) {
            $value = $row[$column->key] ?? null;
            $cells[] = match (true) {
                $value === null || $value === '' => null,
                $column->kind === ColumnKind::Percent => (int) $value / 10000,
                $column->kind === ColumnKind::Date => $this->excelDate((string) $value),
                $column->kind === ColumnKind::DateTime => $this->excelDate((string) $value),
                $column->kind === ColumnKind::Text => (string) $value,
                default => (int) $value,
            };
        }

        return $cells;
    }

    private function excelDate(string $value): float|string
    {
        $time = strtotime($value.(strlen($value) <= 10 ? ' 00:00:00' : ''));

        return $time === false ? $value : (float) Date::PHPToExcel($time);
    }

    private static function letter(int $index): string
    {
        $letter = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letter = chr(65 + ($n - 1) % 26).$letter;
        }

        return $letter;
    }
}
