<?php

declare(strict_types=1);

namespace Src\Pharmacy\Contracts;

final readonly class MedicineInfo
{
    public function __construct(
        public string $id,
        public string $name,
        public string $unit,
        public int $sellPriceAmount,
        public ?string $drugClass,
        public bool $active,
    ) {}
}
