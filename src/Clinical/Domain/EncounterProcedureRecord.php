<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Tindakan pada kunjungan beserta salinan kode, nama, dan tarif katalog saat dicatat (MST-08, M7).
 */
final readonly class EncounterProcedureRecord
{
    public function __construct(
        public string $id,
        public string $encounterId,
        public string $procedureId,
        public string $code,
        public string $name,
        public string $scope,
        public ?int $toothFdi,
        public ?string $surfaces,
        public ?string $jaw,
        public int $quantity,
        public int $unitPriceAmount,
        public ?string $notes,
        public string $performedBy,
        public DateTimeImmutable $performedAt,
        public ?string $planItemId = null,
        public ?string $resultConditionCode = null,
        public ?string $odontogramStatus = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'procedure_id' => $this->procedureId,
            'code' => $this->code,
            'name' => $this->name,
            'scope' => $this->scope,
            'tooth_fdi' => $this->toothFdi,
            'surfaces' => $this->surfaces,
            'jaw' => $this->jaw,
            'quantity' => $this->quantity,
            'unit_price_amount' => $this->unitPriceAmount,
            'subtotal_amount' => $this->quantity * $this->unitPriceAmount,
            'notes' => $this->notes,
            'plan_item_id' => $this->planItemId,
            'result_condition_code' => $this->resultConditionCode,
            'odontogram_status' => $this->odontogramStatus,
        ];
    }
}
