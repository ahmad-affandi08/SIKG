<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use Src\Reporting\Domain\DateRange;
use Src\Reporting\Domain\DoctorFeeScheme;
use Src\Reporting\Domain\MonthlyFee;

/**
 * Port jasa dokter per bulan untuk slip (P2-09); implementasinya membaca tagihan final, tindakan, dan pesanan lab.
 */
interface DoctorFeeStatementSource
{
    /**
     * @return array<string, array{doctor: string, scheme: DoctorFeeScheme, months: list<MonthlyFee>}> id dokter => jasa per bulan
     */
    public function statements(DateRange $range, ?string $doctorId = null, ?string $methodId = null): array;
}
