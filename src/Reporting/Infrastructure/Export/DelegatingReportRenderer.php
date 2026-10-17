<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Reporting\Application\ReportRenderer;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\ReportResult;

final readonly class DelegatingReportRenderer implements ReportRenderer
{
    public function __construct(private PdfReportWriter $pdf, private LetterheadProvider $letterhead) {}

    public function render(ExportFormat $format, ReportResult $result): string
    {
        return match ($format) {
            ExportFormat::Pdf => $this->pdf->render($result),
            ExportFormat::Xlsx => Excel::raw(new ReportWorkbook($result, $this->letterhead->forReceipt()->clinicName), ExcelWriter::XLSX),
        };
    }
}
