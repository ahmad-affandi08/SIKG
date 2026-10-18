<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Online;

/**
 * Status niat bayar online (P3-05). `Mismatch` berarti uang sudah diterima penyedia tetapi tidak dapat dicatat ke tagihan (misalnya
 * tagihan sudah lunas atau dibatalkan, atau jumlah tidak cocok) sehingga perlu diselesaikan petugas (refund lewat penyedia).
 */
enum IntentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Mismatch = 'mismatch';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Lunas',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
            self::Failed => 'Gagal',
            self::Mismatch => 'Perlu diselesaikan',
        };
    }
}
