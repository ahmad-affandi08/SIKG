<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

final readonly class PlanItemRecord
{
    public function __construct(
        public string $id,
        public string $planId,
        public int $sequence,
        public string $procedureId,
        public string $procedureCode,
        public string $procedureName,
        public ?int $toothFdi,
        public int $quantity,
        public int $unitPriceAmount,
        public int $estimateAmount,
        public PlanItemStatus $status,
        public ?string $notes,
        public ?string $encounterId,
        public ?DateTimeImmutable $completedAt,
    ) {}
}
