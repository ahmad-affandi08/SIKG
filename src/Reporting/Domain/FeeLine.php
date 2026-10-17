<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Jasa satu jenis tindakan dalam satu bulan: jumlah, nilai tindakan (setelah diskon item), aturan yang berlaku, dan jasanya.
 */
final readonly class FeeLine
{
    public function __construct(
        public string $code,
        public string $name,
        public int $quantity,
        public int $base,
        public string $rule,
        public int $fee,
    ) {}
}
