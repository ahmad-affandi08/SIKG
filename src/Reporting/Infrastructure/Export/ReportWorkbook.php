<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportResult;

/**
 * Buku kerja Excel: satu lembar "Ringkasan" (judul, rentang, angka ringkasan, catatan definisi) lalu satu lembar per tabel.
 */
final class ReportWorkbook implements Export, WithMultipleSheets
{
    private const MAX_SHEET_TITLE = 31;

    public function __construct(private readonly ReportResult $result, private readonly string $clinicName) {}

    public function sheets(): array
    {
        $sheets = [$this->summarySheet()];
        foreach ($this->result->tables as $index => $table) {
            $sheets[] = new ReportSheet($this->sheetName($index + 2, $table->title), $table);
        }

        return $sheets;
    }

    private function sheetName(int $number, string $title): string
    {
        $clean = preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title) ?? $title;

        return mb_substr($number.' '.$clean, 0, self::MAX_SHEET_TITLE);
    }

    private function summarySheet(): FromArray
    {
        $rows = [
            [$this->clinicName],
            [$this->result->key->code().' '.$this->result->key->title()],
            ['Periode', CellFormatter::date($this->result->range->from).' - '.CellFormatter::date($this->result->range->to)],
            [],
        ];
        foreach ($this->result->metrics as $metric) {
            $rows[] = [$metric->label, $metric->value === null ? '-' : ($metric->kind === ColumnKind::Text ? (string) $metric->value : CellFormatter::text($metric->kind, $metric->value))];
        }
        if ($this->result->notes !== []) {
            $rows[] = [];
            $rows[] = ['Catatan'];
            foreach ($this->result->notes as $note) {
                $rows[] = [$note];
            }
        }

        return new class($rows) implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithTitle
        {
            use WritesTextAsStrings;

            /** @param array<int, array<array-key, mixed>> $rows */
            public function __construct(private readonly array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function title(): string
            {
                return '1 Ringkasan';
            }
        };
    }
}
