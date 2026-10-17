<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Scheduling\Domain\InvalidAppointment;
use Src\Shared\Application\Clock;

/**
 * Konversi antara waktu lokal klinik (yang dilihat pengguna) dan UTC (yang disimpan).
 */
final readonly class ClinicTime
{
    public function __construct(private ClinicCalendar $calendar, private Clock $clock) {}

    public function zone(): DateTimeZone
    {
        return new DateTimeZone($this->calendar->timezone());
    }

    /** Hari ini menurut zona waktu klinik, YYYY-MM-DD. */
    public function today(): string
    {
        return $this->toLocal($this->clock->now())->format('Y-m-d');
    }

    public function toLocal(DateTimeImmutable $utc): DateTimeImmutable
    {
        return $utc->setTimezone($this->zone());
    }

    /**
     * @param  string  $date  YYYY-MM-DD lokal klinik
     * @param  string  $time  HH:MM lokal klinik
     * @return DateTimeImmutable waktu itu dalam UTC
     */
    public function parse(string $date, string $time): DateTimeImmutable
    {
        $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", $this->zone());
        if ($local === false || $local->format('Y-m-d H:i') !== "{$date} {$time}") {
            throw new InvalidAppointment('appointment.invalid_time', 'Tanggal atau jam janji tidak valid.');
        }

        return $local->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Batas UTC untuk seluruh hari lokal dari `$fromDate` sampai `$toDate` (keduanya termasuk).
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public function range(string $fromDate, string $toDate): array
    {
        $utc = new DateTimeZone('UTC');
        $from = $this->parse($fromDate, '00:00');
        $to = DateTimeImmutable::createFromFormat('!Y-m-d', $toDate, $this->zone());
        if ($to === false) {
            throw new InvalidAppointment('appointment.invalid_time', 'Tanggal tidak valid.');
        }

        return [$from, $to->modify('+1 day')->setTimezone($utc)];
    }
}
