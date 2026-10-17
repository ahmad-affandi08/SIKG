<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Tabel hasil laporan. Baris berupa peta kunci kolom ke nilai; `totals` (bila ada) memakai kunci yang sama.
 */
final readonly class ReportTable
{
    /** Batas baris per tabel agar respons dan berkas ekspor tetap muat di memori dan batas waktu hosting. */
    public const MAX_ROWS = 2000;

    /**
     * @param  list<ReportColumn>  $columns
     * @param  list<array<string, int|string|null>>  $rows
     * @param  array<string, int|string|null>|null  $totals
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public bool $truncated = false,
        public ?string $emptyText = null,
    ) {}

    /**
     * @return array{title: string, columns: list<array{key: string, label: string, kind: string}>, rows: list<array<string, int|string|null>>, totals: array<string, int|string|null>|null, truncated: bool, empty_text: string|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'columns' => array_map(fn (ReportColumn $c) => $c->toArray(), $this->columns),
            'rows' => $this->rows,
            'totals' => $this->totals,
            'truncated' => $this->truncated,
            'empty_text' => $this->emptyText,
        ];
    }
}
