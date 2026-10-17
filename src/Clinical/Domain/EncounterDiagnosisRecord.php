<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

final readonly class EncounterDiagnosisRecord
{
    public function __construct(
        public string $id,
        public string $encounterId,
        public string $diagnosisId,
        public string $icd10Code,
        public string $name,
        public ?int $toothFdi,
        public ?string $notes,
    ) {}

    /**
     * @return array{id: string, diagnosis_id: string, code: string, name: string, tooth_fdi: ?int, notes: ?string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'diagnosis_id' => $this->diagnosisId, 'code' => $this->icd10Code, 'name' => $this->name, 'tooth_fdi' => $this->toothFdi, 'notes' => $this->notes];
    }
}
