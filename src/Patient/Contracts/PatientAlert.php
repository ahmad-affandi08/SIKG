<?php

declare(strict_types=1);

namespace Src\Patient\Contracts;

final readonly class PatientAlert
{
    public function __construct(public string $type, public string $typeLabel, public string $name, public ?string $severity) {}

    /**
     * @return array{type: string, type_label: string, name: string, severity: ?string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'type_label' => $this->typeLabel, 'name' => $this->name, 'severity' => $this->severity];
    }
}
