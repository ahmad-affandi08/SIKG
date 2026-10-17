<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

final readonly class PrescriptionItemRecord
{
    public function __construct(
        public string $id,
        public ?string $medicineId,
        public string $name,
        public string $dose,
        public string $frequency,
        public string $duration,
        public ?string $instructions,
        public int $quantity,
        public bool $dispenseInClinic,
        public int $unitPriceAmount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'medicine_id' => $this->medicineId,
            'name' => $this->name,
            'dose' => $this->dose,
            'frequency' => $this->frequency,
            'duration' => $this->duration,
            'instructions' => $this->instructions,
            'quantity' => $this->quantity,
            'dispense_in_clinic' => $this->dispenseInClinic,
            'unit_price_amount' => $this->unitPriceAmount,
        ];
    }
}
