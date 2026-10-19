<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\ServiceProfile;

/**
 * Enam peran bawaan (docs/PRD/00 bagian 4). Pemilik klinik dapat menyesuaikan izin peran tanpa mengubah kode.
 */
enum Role: string
{
    case Pemilik = 'pemilik';
    case Admin = 'admin';
    case Resepsionis = 'resepsionis';
    case Dokter = 'dokter';
    case Perawat = 'perawat';
    case Kasir = 'kasir';

    /** Sebutan Dokter mengikuti profil layanan klinik (KLU-02): klinik umum menyebutnya "Dokter". */
    public function label(?ServiceProfile $profile = null): string
    {
        return match ($this) {
            self::Pemilik => 'Pemilik',
            self::Admin => 'Admin',
            self::Resepsionis => 'Resepsionis',
            self::Dokter => $profile?->doctorTitle() ?? 'Dokter gigi',
            self::Perawat => 'Perawat',
            self::Kasir => 'Kasir',
        };
    }
}
