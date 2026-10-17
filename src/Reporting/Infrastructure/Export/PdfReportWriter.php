<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure\Export;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Reporting\Domain\ColumnKind;
use Src\Reporting\Domain\ReportResult;
use Src\Reporting\Domain\ReportTable;
use Src\Shared\Application\Clock;
use Src\Shared\Application\PdfRenderer;
use Src\Shared\Application\PdfRequest;

/**
 * Laporan ke PDF dengan dompdf (PHP murni, M21). Tabel lebar memakai kertas lanskap.
 */
final readonly class PdfReportWriter
{
    private const LANDSCAPE_FROM_COLUMNS = 6;

    public function __construct(private LetterheadProvider $letterhead, private Clock $clock, private ClinicCalendar $calendar, private PdfRenderer $renderer) {}

    public function render(ReportResult $result): string
    {
        $head = $this->letterhead->forDocument();
        $wide = false;
        $tables = [];
        foreach ($result->tables as $table) {
            $wide = $wide || count($table->columns) >= self::LANDSCAPE_FROM_COLUMNS;
            $tables[] = $this->prepare($table);
        }

        $metrics = [];
        foreach ($result->metrics as $metric) {
            $metrics[] = ['label' => $metric->label, 'value' => $metric->value === null ? '-' : ($metric->kind === ColumnKind::Text ? (string) $metric->value : CellFormatter::text($metric->kind, $metric->value))];
        }

        $printedAt = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d H:i');

        return $this->renderer->render(new PdfRequest('pdf.report', [
            'letterhead' => $head->toArray(),
            'code' => $result->key->code(),
            'title' => $result->key->title(),
            'from' => CellFormatter::date($result->range->from),
            'to' => CellFormatter::date($result->range->to),
            'printedAt' => CellFormatter::dateTime($printedAt),
            'metrics' => $metrics,
            'tables' => $tables,
            'notes' => $result->notes,
        ], $result->key->code().' '.$result->key->title(), landscape: $wide));
    }

    /**
     * @return array{title: string, headers: list<array{label: string, right: bool}>, rows: list<list<string>>, totals: list<string>|null, truncated: bool, empty: string|null}
     */
    private function prepare(ReportTable $table): array
    {
        $headers = [];
        foreach ($table->columns as $column) {
            $headers[] = ['label' => $column->label, 'right' => CellFormatter::isNumeric($column->kind)];
        }
        $format = function (array $row) use ($table): array {
            $cells = [];
            foreach ($table->columns as $column) {
                $cells[] = CellFormatter::text($column->kind, $row[$column->key] ?? null);
            }

            return $cells;
        };

        return [
            'title' => $table->title,
            'headers' => $headers,
            'rows' => array_map($format, $table->rows),
            'totals' => $table->totals === null ? null : $format($table->totals),
            'truncated' => $table->truncated,
            'empty' => $table->emptyText,
        ];
    }
}
