<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Satu blok jam praktik pada satu hari (1 = Senin ... 7 = Minggu), jam lokal klinik.
 */
final readonly class ScheduleSlot
{
    public function __construct(public int $weekday, public string $start, public string $end)
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new InvalidDoctorData('doctor.invalid_schedule', 'Hari praktik tidak valid.');
        }
        foreach ([$start, $end] as $time) {
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
                throw new InvalidDoctorData('doctor.invalid_schedule', 'Jam praktik harus berformat JJ:MM.');
            }
        }
        if ($end <= $start) {
            throw new InvalidDoctorData('doctor.invalid_schedule', 'Jam selesai harus setelah jam mulai.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->weekday === $other->weekday && $this->start < $other->end && $other->start < $this->end;
    }

    /**
     * @return array{weekday: int, start: string, end: string}
     */
    public function toArray(): array
    {
        return ['weekday' => $this->weekday, 'start' => $this->start, 'end' => $this->end];
    }
}
