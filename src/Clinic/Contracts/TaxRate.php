<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Tarif pajak tagihan yang diatur klinik (KSR-09), dalam basis poin (1% = 100). Bawaan 0: layanan kesehatan umumnya
 * dibebaskan PPN, namun klinik perlu memastikannya dengan konsultan pajak. Billing menyalin tarif ini ke tagihan saat dibuat.
 */
interface TaxRate
{
    public function basisPoints(): int;
}
