<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Batas diskon per peran yang diatur klinik (KSR-02), dibaca Billing. Dalam basis poin dari nilai yang didiskon
 * (100% = 10000). Pengguna dengan beberapa peran memakai batas tertinggi; peran tanpa batas tercatat bernilai 0.
 */
interface DiscountLimits
{
    /**
     * @param  list<string>  $roles
     */
    public function basisPointsFor(array $roles): int;
}
