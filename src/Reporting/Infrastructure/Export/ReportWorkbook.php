<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;
use Src\Reporting\Domain\ReportResult;

/**
 * Buku kerja Excel: satu lembar "Ringkasan" lalu satu lembar per tabel, semuanya dengan gaya seragam (SheetStyler) dan
 * properti berkas (judul, penyusun, perusahaan) yang terisi agar tampak sebagai dokumen resmi klinik.
 */
final class ReportWorkbook implements Export, WithMultipleSheets, WithProperties
{
    private const MAX_SHEET_TITLE = 31;

    public function __construct(private readonly ReportResult $result, private readonly ReportContext $context) {}

    public function sheets(): array
    {
        $sheets = [new SummarySheet($this->result, $this->context)];
        foreach ($this->result->tables as $index => $table) {
            $sheets[] = new ReportSheet($this->sheetName($index + 2, $table->title), $table, $this->context);
        }

        return $sheets;
    }

    public function properties(): array
    {
        return [
            'creator' => $this->context->clinicName,
            'lastModifiedBy' => 'SIKG',
            'title' => $this->context->heading,
            'subject' => 'Periode '.$this->context->period,
            'description' => 'Laporan dibuat otomatis oleh SIKG pada '.$this->context->printedAt.'.',
            'category' => 'Laporan klinik',
            'company' => $this->context->clinicName,
        ];
    }

    private function sheetName(int $number, string $title): string
    {
        $clean = preg_replace('/[\[\]:*?\/\\\\]/', ' ', $title) ?? $title;

        return mb_substr($number.' '.$clean, 0, self::MAX_SHEET_TITLE);
    }
}
