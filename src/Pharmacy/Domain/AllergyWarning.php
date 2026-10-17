<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use Src\Shared\Domain\DomainException;

/**
 * OBT-02: kelas obat cocok dengan alergi pasien. Resep ditahan sampai dokter mengonfirmasi dengan sadar.
 */
final class AllergyWarning extends DomainException
{
    /**
     * @param  list<string>  $matches  contoh "Amoksisilin (penisilin) - alergi Penisilin"
     */
    public function __construct(public readonly array $matches)
    {
        parent::__construct('prescription.allergy_warning', 'Peringatan alergi: '.implode('; ', $matches).'. Konfirmasi bila tetap diresepkan.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
