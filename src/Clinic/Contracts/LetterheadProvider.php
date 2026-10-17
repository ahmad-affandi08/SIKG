<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

interface LetterheadProvider
{
    /** Kop yang sama untuk semua cetakan: invoice, kwitansi, resep, dokumen klinis, odontogram, dan laporan. */
    public function forDocument(): Letterhead;
}
