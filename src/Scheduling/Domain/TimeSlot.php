<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Rentang waktu setengah terbuka [mulai, selesai): janji yang selesai tepat saat janji lain mulai tidak bentrok.
 */
final readonly class TimeSlot
{
    public const MIN_MINUTES = 10;

    public const MAX_MINUTES = 480;

    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end)
    {
        $minutes = intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
        if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
            throw new InvalidAppointment('appointment.invalid_duration', 'Durasi janji harus antara '.self::MIN_MINUTES.' dan '.self::MAX_MINUTES.' menit.');
        }
    }

    public static function of(DateTimeImmutable $start, int $minutes): self
    {
        return new self($start, $start->modify("+{$minutes} minutes"));
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function minutes(): int
    {
        return intdiv($this->end->getTimestamp() - $this->start->getTimestamp(), 60);
    }
}
