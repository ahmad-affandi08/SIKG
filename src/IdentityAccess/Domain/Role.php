<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

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

    public function label(): string
    {
        return match ($this) {
            self::Pemilik => 'Pemilik',
            self::Admin => 'Admin',
            self::Resepsionis => 'Resepsionis',
            self::Dokter => 'Dokter gigi',
            self::Perawat => 'Perawat',
            self::Kasir => 'Kasir',
        };
    }
}
