<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Gaya lembar Excel yang seragam untuk semua laporan: hanya format (ukuran, tebal, garis, perataan), tanpa warna teks.
 * Aturan: judul di kiri atas, satu baris tajuk tebal berlatar abu muda dengan garis atas dan bawah, angka rata kanan,
 * garis tipis antarbaris, baris total tebal dengan garis ganda, baris tajuk dibekukan dan diulang saat dicetak
 * (ADR-0008). Semua pengaturan cetak diset agar hasil cetak A4 rapi tanpa penyesuaian manual.
 */
final class SheetStyler
{
    public const FONT = 'Calibri';

    /** Kop: 1 nama klinik, 2 alamat dan izin, 3 judul laporan, 4 judul tabel, 5 rentang dan waktu cetak, 6 kosong, 7 tajuk kolom. */
    public const HEADER_ROW = 7;

    private const GRID = 'FFD9D9D9';

    private const HEAD_FILL = 'FFF2F2F2';

    private const INK = 'FF000000';

    public static function base(Worksheet $sheet): void
    {
        $sheet->getParent()?->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(10);
        $sheet->setShowGridlines(false);
        $sheet->getSheetView()->setZoomScale(100);
    }

    /**
     * Kop yang sama di semua lembar (baris 1 sampai 5): nama klinik, alamat dan izin, judul laporan, judul tabel, rentang.
     */
    public static function titleBlock(Worksheet $sheet, bool $withPeriod, int $lastCol): void
    {
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getFont()->setSize(9);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(11);
        $sheet->getRowDimension(3)->setRowHeight(22);
        if ($withPeriod) {
            $sheet->getStyle('A5')->getFont()->setSize(9);
        }
        // Garis ganda di bawah kop seperti kop surat; teks judul meluap ke kanan di sel kosong, tidak melebarkan kolom A.
        $last = Coordinate::stringFromColumnIndex(max(1, $lastCol));
        $sheet->getStyle("A2:{$last}2")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setARGB(self::INK);
        foreach ([1, 2, 3, 4, 5] as $row) {
            $sheet->getStyle("A{$row}:{$last}{$row}")->getAlignment()->setWrapText(false)->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    /**
     * @param  list<bool>  $numeric  per kolom: true bila angka (rata kanan)
     */
    public static function header(Worksheet $sheet, int $row, array $numeric): void
    {
        $last = Coordinate::stringFromColumnIndex(count($numeric));
        $range = "A{$row}:{$last}{$row}";
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEAD_FILL);
        $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::INK);
        $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::INK);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(24);
        foreach ($numeric as $index => $isNumeric) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getStyle("{$letter}{$row}")->getAlignment()->setHorizontal($isNumeric ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
        }
    }

    /**
     * @param  list<bool>  $numeric
     */
    public static function body(Worksheet $sheet, int $firstRow, int $lastRow, array $numeric, bool $wrapText): void
    {
        if ($lastRow < $firstRow) {
            return;
        }
        $last = Coordinate::stringFromColumnIndex(count($numeric));
        $style = $sheet->getStyle("A{$firstRow}:{$last}{$lastRow}");
        $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GRID);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        foreach ($numeric as $index => $isNumeric) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $column = $sheet->getStyle("{$letter}{$firstRow}:{$letter}{$lastRow}")->getAlignment();
            $column->setHorizontal($isNumeric ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
            if ($wrapText && ! $isNumeric) {
                $column->setWrapText(true);
            }
        }
    }

    public static function totals(Worksheet $sheet, int $row, int $cols): void
    {
        $last = Coordinate::stringFromColumnIndex($cols);
        $style = $sheet->getStyle("A{$row}:{$last}{$row}");
        $style->getFont()->setBold(true);
        $style->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::INK);
        $style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setARGB(self::INK);
    }

    /**
     * @param  list<int|float>  $widths  lebar kolom dalam karakter
     */
    public static function widths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }
    }

    /**
     * Lebar kolom dari isi terpanjang (tajuk dan sel), dibatasi agar tabel tetap muat satu halaman lebar.
     *
     * @param  list<list<string>>  $columns  per kolom: daftar teks tampilan (tajuk dulu)
     * @return list<float>
     */
    public static function fitWidths(array $columns, int $min = 10, int $max = 46): array
    {
        $widths = [];
        foreach ($columns as $cells) {
            $longest = 0;
            foreach ($cells as $text) {
                $longest = max($longest, mb_strlen($text));
            }
            $widths[] = (float) max($min, min($max, $longest + 3));
        }

        return $widths;
    }

    /**
     * Pengaturan cetak: A4, muat satu halaman lebar, tajuk tabel diulang, nomor halaman, dan keterangan di kepala dan kaki.
     */
    public static function print(Worksheet $sheet, ReportContext $ctx, string $sheetTitle, int $cols, int $headerRow, int $lastRow): void
    {
        $last = Coordinate::stringFromColumnIndex(max(1, $cols));
        $setup = $sheet->getPageSetup();
        $setup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $setup->setOrientation($cols >= 6 ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $setup->setFitToWidth(1);
        $setup->setFitToHeight(0);
        $setup->setHorizontalCentered(false);
        $setup->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $setup->setPrintArea("A1:{$last}".max($lastRow, $headerRow));

        $margins = $sheet->getPageMargins();
        $margins->setTop(0.75)->setBottom(0.75)->setLeft(0.6)->setRight(0.6)->setHeader(0.3)->setFooter(0.3);

        $hf = $sheet->getHeaderFooter();
        $hf->setOddHeader('&L&8'.self::escape($ctx->clinicName).'&R&8'.self::escape($ctx->heading));
        $hf->setOddFooter('&L&8Dicetak '.self::escape($ctx->printedAt).' · '.self::escape($sheetTitle).'&R&8Halaman &P dari &N');
    }

    public static function freezeBelow(Worksheet $sheet, int $headerRow): void
    {
        $sheet->freezePane('A'.($headerRow + 1));
    }

    private static function escape(string $text): string
    {
        return str_replace('&', '&&', $text);
    }
}
