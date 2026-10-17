<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

final readonly class StockMovementRecord
{
    public function __construct(
        public string $id,
        public string $medicineId,
        public MovementType $type,
        public int $quantity,
        public int $balanceAfter,
        public ?string $referenceType,
        public ?string $reason,
        public ?string $batchNo,
        public ?string $expiryDate,
        public string $createdBy,
        public DateTimeImmutable $createdAt,
    ) {}
}
