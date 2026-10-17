<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportResult;

/**
 * Lembar "Ringkasan": rentang, waktu cetak, angka ringkasan bertipe (bukan teks) dengan format sel, lalu catatan definisi.
 */
final class SummarySheet implements FromArray, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithTitle
{
    use WritesTextAsStrings;

    public const TITLE = '1 Ringkasan';

    private const HEADER_ROW = SheetStyler::HEADER_ROW;

    private const WIDTH_LABEL = 44.0;

    private const WIDTH_VALUE = 26.0;

    private const CHARS_PER_LINE = 78;

    public function __construct(private readonly ReportResult $result, private readonly ReportContext $context) {}

    public function title(): string
    {
        return self::TITLE;
    }

    public function array(): array
    {
        $rows = [
            [mb_strtoupper($this->context->clinicName)],
            [$this->context->kopLine()],
            [$this->context->heading],
            ['Ringkasan'],
            [null],
            [null],
            ['Keterangan', 'Nilai'],
            ['Periode', $this->context->period],
            ['Dicetak', $this->context->printedAt],
        ];
        foreach ($this->result->metrics as $metric) {
            $rows[] = [$metric->label, $this->value($metric->kind, $metric->value)];
        }
        if ($this->result->notes !== []) {
            $rows[] = [null];
            $rows[] = ['Catatan definisi'];
            foreach ($this->result->notes as $note) {
                $rows[] = [$note];
            }
        }

        $rows[] = [null];
        $rows[] = ['Disusun oleh,', 'Mengetahui,'];
        $rows[] = [null];
        $rows[] = [null];
        $rows[] = [null];
        $rows[] = ['Nama dan jabatan', 'Pimpinan klinik'];

        return $rows;
    }

    /**
     * @return array<class-string, callable(AfterSheet): void>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $metricsFirst = self::HEADER_ROW + 3;
                $metricsLast = self::HEADER_ROW + 2 + count($this->result->metrics);

                SheetStyler::base($sheet);
                SheetStyler::titleBlock($sheet, false, 2);
                SheetStyler::header($sheet, self::HEADER_ROW, [false, true]);
                SheetStyler::body($sheet, self::HEADER_ROW + 1, $metricsLast, [false, true], false);
                SheetStyler::widths($sheet, [self::WIDTH_LABEL, self::WIDTH_VALUE]);

                foreach ($this->result->metrics as $index => $metric) {
                    $format = match ($metric->kind) {
                        ColumnKind::Money => '"Rp" #,##0;-"Rp" #,##0',
                        ColumnKind::Integer => '#,##0',
                        ColumnKind::Percent => '0.0%',
                        ColumnKind::Minutes => '#,##0 "mnt"',
                        default => null,
                    };
                    if ($format !== null) {
                        $sheet->getStyle('B'.($metricsFirst + $index))->getNumberFormat()->setFormatCode($format);
                    }
                }

                $row = $metricsLast + 1;
                if ($this->result->notes !== []) {
                    $sheet->getStyle('A'.($row + 1))->getFont()->setBold(true);
                    $row += 2;
                    foreach ($this->result->notes as $note) {
                        $sheet->mergeCells("A{$row}:B{$row}");
                        $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP)->setHorizontal(Alignment::HORIZONTAL_LEFT);
                        $sheet->getRowDimension($row)->setRowHeight(13.5 * max(1, (int) ceil(mb_strlen($note) / self::CHARS_PER_LINE)) + 3);
                        $row++;
                    }
                }

                // Tanda tangan: dua kolom, disusun oleh di kiri dan mengetahui di kanan.
                $sign = $row + 1;
                $sheet->getStyle("A{$sign}:B{$sign}")->getFont()->setBold(true);
                $line = $sheet->getStyle('A'.($sign + 4).':B'.($sign + 4));
                $line->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $line->getFont()->setSize(8);
                $line->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);

                SheetStyler::freezeBelow($sheet, self::HEADER_ROW);
                SheetStyler::print($sheet, $this->context, 'Ringkasan', 2, self::HEADER_ROW, $sign + 4);
                $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(2).'1')->getFont()->setBold(true);
            },
        ];
    }

    private function value(ColumnKind $kind, int|string|null $value): int|float|string
    {
        return match (true) {
            $value === null || $value === '' => '-',
            $kind === ColumnKind::Text => (string) $value,
            $kind === ColumnKind::Percent => (int) $value / 10000,
            default => (int) $value,
        };
    }
}
