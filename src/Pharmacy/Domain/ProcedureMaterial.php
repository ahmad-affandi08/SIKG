<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

/**
 * Bahan habis pakai standar per tindakan: tiap tindakan yang dicatat mengurangi stok sebesar `quantity` per satuan tindakan.
 */
final readonly class ProcedureMaterial
{
    public function __construct(
        public string $id,
        public string $procedureId,
        public string $medicineId,
        public int $quantity,
        public bool $active,
    ) {}
}
