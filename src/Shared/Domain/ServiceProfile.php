<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Profil layanan klinik (KLU-01): satu aplikasi untuk klinik gigi dan klinik umum. Profil hanya mengatur fitur dan istilah
 * yang tampil; data tidak pernah dihapus atau dipindah saat profil berganti (M5). Klinik yang sudah ada tetap Gigi.
 * Profil rawat inap menyusul bersama modulnya (INP-*), tidak ditawarkan sebelum modul itu ada.
 */
enum ServiceProfile: string
{
    case Dental = 'dental';
    case General = 'general';

    public static function default(): self
    {
        return self::Dental;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dental => 'Klinik gigi',
            self::General => 'Klinik umum',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dental => 'Odontogram, tindakan per gigi, ortodonti, dan pesanan lab gigi tersedia.',
            self::General => 'Pemeriksaan umum tanpa odontogram, ortodonti, dan pesanan lab gigi. Data yang sudah ada tidak dihapus.',
        };
    }

    /** Odontogram, tindakan per gigi, ortodonti, dan pesanan lab gigi. */
    public function dental(): bool
    {
        return $this === self::Dental;
    }

    /** Sebutan dokter pada antarmuka dan dokumen. */
    public function doctorTitle(): string
    {
        return match ($this) {
            self::Dental => 'Dokter gigi',
            self::General => 'Dokter',
        };
    }
}
