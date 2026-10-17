<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Jadwal praktik mingguan dokter. Blok pada hari yang sama tidak boleh saling tumpang tindih.
 */
final readonly class WeeklySchedule
{
    /** @var list<ScheduleSlot> */
    public array $slots;

    /**
     * @param  list<ScheduleSlot>  $slots
     */
    public function __construct(array $slots)
    {
        foreach ($slots as $i => $slot) {
            foreach (array_slice($slots, $i + 1) as $other) {
                if ($slot->overlaps($other)) {
                    throw new InvalidDoctorData('doctor.schedule_overlap', 'Jam praktik pada hari yang sama saling tumpang tindih.');
                }
            }
        }

        usort($slots, fn (ScheduleSlot $a, ScheduleSlot $b) => [$a->weekday, $a->start] <=> [$b->weekday, $b->start]);
        $this->slots = $slots;
    }

    public function covers(int $weekday, string $time): bool
    {
        foreach ($this->slots as $slot) {
            if ($slot->weekday === $weekday && $time >= $slot->start && $time < $slot->end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{weekday: int, start: string, end: string}>
     */
    public function toArray(): array
    {
        return array_map(fn (ScheduleSlot $s) => $s->toArray(), $this->slots);
    }
}
