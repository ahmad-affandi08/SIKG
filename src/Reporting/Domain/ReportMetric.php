<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Satu angka ringkasan di atas tabel. Nilai bilangan bulat (rupiah, jumlah, basis poin, menit) atau teks.
 */
final readonly class ReportMetric
{
    public function __construct(public string $label, public int|string|null $value, public ColumnKind $kind = ColumnKind::Integer) {}

    /**
     * @return array{label: string, value: int|string|null, kind: string}
     */
    public function toArray(): array
    {
        return ['label' => $this->label, 'value' => $this->value, 'kind' => $this->kind->value];
    }
}
