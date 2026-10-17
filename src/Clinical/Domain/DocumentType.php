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

    /** Awalan nomor dokumen: IC/2025/0001, SKD/2025/0001, RJK/2025/0001. */
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
}
