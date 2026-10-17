<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Jasa satu dokter pada satu bulan: jasa kotor dari tindakan, potongan biaya lab, jasa bersih, dan potongan yang tidak tertutup jasa.
 */
final readonly class MonthlyFee
{
    /**
     * @param  list<FeeLine>  $lines
     */
    public function __construct(
        public string $month,
        public array $lines,
        public int $base,
        public int $gross,
        public int $labCost,
        public int $labDeduction,
        public int $net,
        public int $uncovered,
    ) {}
}
