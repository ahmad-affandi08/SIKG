<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

final readonly class StockCountLine
{
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $medicineId,
        public int $systemQuantity,
        public ?int $countedQuantity,
    ) {}
}
