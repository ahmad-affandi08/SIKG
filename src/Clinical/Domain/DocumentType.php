<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Jenis dokumen klinis yang dicetak untuk tanda tangan basah (RM-09 informed consent, RM-10 surat keterangan dan rujukan).
 */
enum DocumentType: string
{
    case Consent = 'consent';
    case Certificate = 'certificate';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::Consent => 'Informed consent',
            self::Certificate => 'Surat keterangan',
            self::Referral => 'Surat rujukan',
        };
    }

    /** Judul pada dokumen cetak. */
    public function heading(): string
    {
        return match ($this) {
            self::Consent => 'PERSETUJUAN TINDAKAN KEDOKTERAN GIGI',
            self::Certificate => 'SURAT KETERANGAN',
            self::Referral => 'SURAT RUJUKAN',
        };
    }

    /** Awalan nomor dokumen: 001/IC/VI/2025, 001/SKD/VI/2025, 001/RJK/VI/2025 (nomor urut, kode, bulan Romawi, tahun). */
    public function numberPrefix(): string
    {
        return match ($this) {
            self::Consent => 'IC',
            self::Certificate => 'SKD',
            self::Referral => 'RJK',
        };
    }

    /** Informed consent memiliki kolom tanda tangan pasien atau wali dan saksi; surat hanya dokter. */
    public function needsPatientSignature(): bool
    {
        return $this === self::Consent;
    }

    /** Surat rujukan memakai blok Nomor, Lampiran, Perihal (kaidah surat resmi); dokumen lain memakai judul bernomor. */
    public function isOfficialLetter(): bool
    {
        return $this === self::Referral;
    }

    /** Perihal pada surat resmi. */
    public function subject(): string
    {
        return match ($this) {
            self::Referral => 'Rujukan pasien',
            default => $this->label(),
        };
    }

    /** Sebutan penanda tangan dokter pada blok tanda tangan. */
    public function signerRole(): string
    {
        return match ($this) {
            self::Consent => 'Dokter yang menjelaskan',
            self::Certificate => 'Dokter pemeriksa',
            self::Referral => 'Dokter pengirim',
        };
    }
}
