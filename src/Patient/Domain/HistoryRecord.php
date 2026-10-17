<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

final readonly class HistoryRecord
{
    public function __construct(public string $id, public MedicalHistoryEntry $entry) {}

    /**
     * @return array{id: string, type: string, name: string, severity: ?string, notes: ?string, is_warning: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->entry->type->value,
            'name' => $this->entry->name,
            'severity' => $this->entry->severity?->value,
            'notes' => $this->entry->notes,
            'is_warning' => $this->entry->isWarning(),
        ];
    }
}
