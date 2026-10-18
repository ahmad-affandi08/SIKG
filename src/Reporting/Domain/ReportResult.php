<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Hasil satu laporan: angka ringkasan, tabel, dan catatan definisi. Bentuk yang sama dipakai halaman, PDF, dan Excel.
 */
final readonly class ReportResult
{
    /**
     * @param  list<ReportMetric>  $metrics
     * @param  list<ReportTable>  $tables
     * @param  list<string>  $notes
     */
    public function __construct(
        public ReportKey $key,
        public DateRange $range,
        public array $metrics,
        public array $tables,
        public array $notes = [],
    ) {}

    /**
     * @return array{key: string, title: string, from: string, to: string, metrics: list<array{label: string, value: int|string|null, kind: string}>, tables: list<array<string, mixed>>, notes: list<string>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key->value,
            'title' => $this->key->title(),
            'from' => $this->range->from,
            'to' => $this->range->to,
            'metrics' => array_map(fn (ReportMetric $m) => $m->toArray(), $this->metrics),
            'tables' => array_map(fn (ReportTable $t) => $t->toArray(), $this->tables),
            'notes' => $this->notes,
        ];
    }
}
