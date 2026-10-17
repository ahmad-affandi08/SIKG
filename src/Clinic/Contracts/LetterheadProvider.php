<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

interface LetterheadProvider
{
    /** Kop untuk resep (memakai header resep). */
    public function forPrescription(): Letterhead;

    /** Kop untuk invoice dan kwitansi (memakai header kwitansi). */
    public function forReceipt(): Letterhead;
}
