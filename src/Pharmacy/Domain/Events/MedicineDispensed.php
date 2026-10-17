<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * Obat dijual di klinik lewat resep. Billing menambah item draft tagihan dengan salinan harga jual (KSR-01, OBT-04).
 */
final readonly class MedicineDispensed implements DomainEvent
{
    public function __construct(
        public string $encounterId,
        public string $prescriptionItemId,
        public string $patientId,
        public string $medicineName,
        public int $quantity,
        public int $unitPriceAmount,
        private DateTimeImmutable $at,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'pharmacy.medicine_dispensed';
    }
}
