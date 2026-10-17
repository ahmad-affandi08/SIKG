<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

/**
 * Status janji (JDW-04): Dijadwalkan, Dikonfirmasi, Check-in, Sedang diperiksa, Selesai, Batal, Tidak datang.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Dijadwalkan',
            self::Confirmed => 'Dikonfirmasi',
            self::CheckedIn => 'Check-in',
            self::InProgress => 'Sedang diperiksa',
            self::Completed => 'Selesai',
            self::Cancelled => 'Batal',
            self::NoShow => 'Tidak datang',
        };
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::CheckedIn, self::Cancelled, self::NoShow],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canBecome(self $target): bool
    {
        return in_array($target, $this->next(), true);
    }

    /** Janji yang masih menempati slot dokter dan kursi. */
    public function occupiesSlot(): bool
    {
        return $this !== self::Cancelled && $this !== self::NoShow;
    }

    /** Janji yang belum diperiksa dan masih boleh dijadwal ulang. */
    public function canReschedule(): bool
    {
        return $this === self::Scheduled || $this === self::Confirmed;
    }

    /**
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return array_values(array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->occupiesSlot())));
    }
}
