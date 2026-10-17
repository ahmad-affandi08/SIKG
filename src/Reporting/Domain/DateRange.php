<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Rentang tanggal laporan menurut kalender klinik, kedua ujung termasuk (YYYY-MM-DD). Dibatasi agar agregasi tetap
 * selesai dalam batas waktu hosting.
 */
final readonly class DateRange
{
    public const MAX_DAYS = 366;

    private function __construct(public string $from, public string $to) {}

    public static function between(string $from, string $to): self
    {
        $start = self::parse($from);
        $end = self::parse($to);
        if ($start > $end) {
            throw new InvalidReportRequest('report.range_invalid', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
        }
        if ($start->diff($end)->days >= self::MAX_DAYS) {
            throw new InvalidReportRequest('report.range_too_long', 'Rentang tanggal paling panjang '.self::MAX_DAYS.' hari. Persempit rentang atau bagi menjadi beberapa laporan.');
        }

        return new self($start->format('Y-m-d'), $end->format('Y-m-d'));
    }

    public static function day(string $date): self
    {
        return self::between($date, $date);
    }

    private static function parse(string $date): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidReportRequest('report.range_invalid', 'Tanggal tidak valid.');
        }

        return $parsed;
    }

    public function days(): int
    {
        return (int) self::parse($this->from)->diff(self::parse($this->to))->days + 1;
    }

    /** Rentang dengan panjang sama yang berakhir sehari sebelum rentang ini. */
    public function previous(): self
    {
        $from = self::parse($this->from);
        $length = $this->days();

        return new self(
            $from->modify('-'.$length.' days')->format('Y-m-d'),
            $from->modify('-1 day')->format('Y-m-d'),
        );
    }

    /**
     * Seluruh tanggal dalam rentang, berurutan.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];
        $cursor = self::parse($this->from);
        $end = self::parse($this->to);
        while ($cursor <= $end) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Seluruh bulan (YYYY-MM) yang tersentuh rentang, berurutan.
     *
     * @return list<string>
     */
    public function months(): array
    {
        $months = [];
        foreach ($this->dates() as $date) {
            $month = substr($date, 0, 7);
            if ($months === [] || end($months) !== $month) {
                $months[] = $month;
            }
        }

        return $months;
    }
}
