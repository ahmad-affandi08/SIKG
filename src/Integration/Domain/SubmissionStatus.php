<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

/**
 * Status pengiriman kunjungan ke SATUSEHAT. `blocked` menunggu tindakan pengguna (data belum lengkap) dan tidak dicoba
 * ulang otomatis; `failed` sudah habis percobaan atau ditolak platform; `skipped` tidak dapat dikirim (contoh pasien tanpa NIK).
 */
enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu kirim',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Blocked => 'Perlu tindakan',
            self::Skipped => 'Dilewati',
        };
    }
}
