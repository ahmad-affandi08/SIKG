<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Booking;

/**
 * Daur hidup permintaan booking online (P3-01): menunggu kode verifikasi, menunggu konfirmasi staf, lalu dikonfirmasi menjadi janji,
 * ditolak, kedaluwarsa, atau dibatalkan pasien. Hanya dua status pertama yang menahan slot.
 */
enum BookingStatus: string
{
    case AwaitingCode = 'awaiting_code';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function holdsSlot(): bool
    {
        return $this === self::AwaitingCode || $this === self::Pending;
    }

    public function label(): string
    {
        return match ($this) {
            self::AwaitingCode => 'Menunggu kode verifikasi',
            self::Pending => 'Menunggu konfirmasi klinik',
            self::Confirmed => 'Dikonfirmasi',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
