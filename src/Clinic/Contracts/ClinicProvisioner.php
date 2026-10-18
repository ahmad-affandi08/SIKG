<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Pembuatan klinik pertama saat pemasangan (OPS). Dipakai konteks lain lewat kontrak ini, bukan lewat model Clinic.
 */
interface ClinicProvisioner
{
    public function anyClinicExists(): bool;

    /** @return string ID klinik baru */
    public function create(string $name, string $timezone): string;
}
