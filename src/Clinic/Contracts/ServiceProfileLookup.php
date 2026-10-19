<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

use Src\Shared\Domain\ServiceProfile;

/**
 * Profil layanan klinik pengguna saat ini (KLU-01). Konteks lain memakai ini untuk menutup fitur khusus gigi di server
 * pada klinik umum; menyembunyikan menu saja tidak cukup (M9).
 */
interface ServiceProfileLookup
{
    public function current(): ServiceProfile;
}
