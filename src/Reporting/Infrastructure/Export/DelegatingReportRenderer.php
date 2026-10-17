<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use DateTimeZone;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Reporting\Application\ReportRenderer;
use Src\Reporting\Domain\ExportFormat;
use Src\Reporting\Domain\ReportResult;
use Src\Shared\Application\Clock;

final readonly class DelegatingReportRenderer implements ReportRenderer
{
    public function __construct(private PdfReportWriter $pdf, private LetterheadProvider $letterhead, private Clock $clock, private ClinicCalendar $calendar) {}

    public function render(ExportFormat $format, ReportResult $result): string
    {
        return match ($format) {
            ExportFormat::Pdf => $this->pdf->render($result),
            ExportFormat::Xlsx => Excel::raw(new ReportWorkbook($result, $this->context($result)), ExcelWriter::XLSX),
        };
    }

    private function context(ReportResult $result): ReportContext
    {
        $printed = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d H:i');

        $head = $this->letterhead->forDocument();

        return new ReportContext(
            $head->clinicName,
            $head->address,
            $head->licenseNo,
            $result->key->code().' '.$result->key->title(),
            CellFormatter::date($result->range->from).' - '.CellFormatter::date($result->range->to),
            CellFormatter::dateTime($printed),
        );
    }
}
