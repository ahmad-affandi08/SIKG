<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use DateTimeImmutable;

/**
 * Jadwal praktik mingguan dokter menurut jam lokal klinik. Janji sah bila seluruh rentangnya berada di dalam satu blok.
 */
final readonly class PracticeHours
{
    /**
     * @param  list<array{weekday: int, start: string, end: string}>  $blocks  weekday 1 = Senin ... 7 = Minggu
     */
    public function __construct(private array $blocks) {}

    /**
     * @param  DateTimeImmutable  $start  waktu lokal klinik
     * @param  DateTimeImmutable  $end  waktu lokal klinik
     */
    public function covers(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            return false;
        }
        $weekday = (int) $start->format('N');
        $from = $start->format('H:i');
        $to = $end->format('H:i');

        foreach ($this->blocks as $block) {
            if ($block['weekday'] === $weekday && $from >= $block['start'] && $to <= $block['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Blok pada hari tertentu.
     *
     * @return list<array{start: string, end: string}>
     */
    public function onWeekday(int $weekday): array
    {
        return array_values(array_map(
            fn (array $b) => ['start' => $b['start'], 'end' => $b['end']],
            array_filter($this->blocks, fn (array $b) => $b['weekday'] === $weekday),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }
}
