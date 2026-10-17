<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

final readonly class ReportColumn
{
    public function __construct(public string $key, public string $label, public ColumnKind $kind = ColumnKind::Text) {}

    /**
     * @return array{key: string, label: string, kind: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'kind' => $this->kind->value];
    }
}
