<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportTable;

/**
 * Satu lembar Excel untuk satu tabel laporan. Uang tetap bilangan bulat rupiah dengan format sel; persen disimpan
 * sebagai pecahan agar format persen Excel benar; tanggal sebagai tanggal Excel.
 */
final class ReportSheet implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    use WritesTextAsStrings;

    private const HEADER_ROW = SheetStyler::HEADER_ROW;

    public function __construct(private readonly string $sheetTitle, private readonly ReportTable $table, private readonly ReportContext $context) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $rows = [
            [mb_strtoupper($this->context->clinicName)],
            [$this->context->kopLine()],
            [$this->context->heading],
            [$this->table->title],
            ['Periode '.$this->context->period.' · Dicetak '.$this->context->printedAt],
            [null],
            array_map(fn ($c) => $c->label, $this->table->columns),
        ];
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
                ColumnKind::Money => '"Rp" #,##0;-"Rp" #,##0',
                ColumnKind::Integer => '#,##0',
                ColumnKind::Percent => '0.0%',
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

    /**
     * @return array<class-string, callable(AfterSheet): void>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $cols = count($this->table->columns);
                $numeric = array_map(fn ($c) => CellFormatter::isNumeric($c->kind), $this->table->columns);
                $first = self::HEADER_ROW + 1;
                $dataLast = self::HEADER_ROW + count($this->table->rows);
                $hasTotals = $this->table->totals !== null;
                $last = $dataLast + ($hasTotals ? 1 : 0);

                SheetStyler::base($sheet);
                SheetStyler::titleBlock($sheet, true, $cols);
                SheetStyler::header($sheet, self::HEADER_ROW, $numeric);
                SheetStyler::body($sheet, $first, $last, $numeric, true);
                if ($hasTotals) {
                    SheetStyler::totals($sheet, $last, $cols);
                }
                SheetStyler::widths($sheet, $this->widths());
                SheetStyler::freezeBelow($sheet, self::HEADER_ROW);
                if ($this->table->rows !== []) {
                    $sheet->setAutoFilter('A'.self::HEADER_ROW.':'.self::letter($cols - 1).$dataLast);
                }
                // Catatan di bawah tabel (dipotong atau kosong) tidak memakai garis tabel.
                $tail = $last + 1;
                if ($this->table->truncated || ($this->table->rows === [] && $this->table->emptyText !== null)) {
                    $sheet->getStyle("A{$tail}")->getFont()->setItalic(true);
                    $sheet->getStyle("A{$tail}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_NONE);
                }
                SheetStyler::print($sheet, $this->context, $this->sheetTitle, $cols, self::HEADER_ROW, max($last, $tail));
            },
        ];
    }

    /**
     * @return list<float>
     */
    private function widths(): array
    {
        $columns = [];
        foreach ($this->table->columns as $column) {
            $texts = [$column->label];
            foreach ([...$this->table->rows, ...($this->table->totals === null ? [] : [$this->table->totals])] as $row) {
                $texts[] = CellFormatter::text($column->kind, $row[$column->key] ?? null);
            }
            $columns[] = $texts;
        }

        return SheetStyler::fitWidths($columns);
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
