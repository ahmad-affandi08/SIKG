<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use Src\Clinic\Contracts\ReminderSettings;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\AppointmentStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\StaffAlerts;

/**
 * JDW-07: ringkasan janji untuk pemilik dan staf klinik, bukan pesan ke pasien. Ringkasan janji besok terbit mulai pukul 09.00
 * hari sebelumnya dan ringkasan janji hari ini mulai pukul 07.00, masing-masing sekali per hari (dedupe) lewat notifikasi dalam
 * aplikasi dan diteruskan ke email atau Telegram klinik. Isinya hanya jumlah dan jam pertama, tanpa nama pasien (M11), sehingga
 * aman diteruskan. Staf membuka jadwal untuk menghubungi pasien sendiri bila perlu. Hanya bila klinik mengaktifkan pengingat.
 */
final readonly class AppointmentDigest
{
    private const TODAY_FROM_HOUR = 7;

    private const TOMORROW_FROM_HOUR = 9;

    public function __construct(
        private AppointmentRepository $appointments,
        private ReminderSettings $settings,
        private ClinicTime $time,
        private StaffAlerts $alerts,
        private Clock $clock,
    ) {}

    /**
     * @return int jumlah ringkasan baru yang terbit
     */
    public function raise(): int
    {
        if (! $this->settings->enabled()) {
            return 0;
        }

        $now = $this->time->toLocal($this->clock->now());
        $hour = (int) $now->format('G');
        $raised = 0;

        if ($hour >= self::TODAY_FROM_HOUR) {
            $raised += $this->digest($now->format('Y-m-d'), 'hari ini', 'Janji hari ini', true);
        }
        if ($hour >= self::TOMORROW_FROM_HOUR) {
            $raised += $this->digest($now->modify('+1 day')->format('Y-m-d'), 'besok', 'Janji besok', false);
        }

        return $raised;
    }

    private function digest(string $date, string $label, string $title, bool $onlyUpcoming): int
    {
        [$from, $to] = $this->time->range($date, $date);
        $now = $this->clock->now();
        $upcoming = array_values(array_filter(
            $this->appointments->between($from, $to),
            fn ($a) => ($a->status === AppointmentStatus::Scheduled || $a->status === AppointmentStatus::Confirmed) && (! $onlyUpcoming || $a->startsAt > $now),
        ));
        if ($upcoming === []) {
            return 0;
        }

        $local = $this->time->toLocal($upcoming[0]->startsAt);
        $first = $local->format('H:i');
        $count = count($upcoming);

        return $this->alerts->raise(new Notification(
            'appointment.digest',
            $title,
            "Ada {$count} janji {$label} ({$local->format('d/m/Y')}), yang pertama pukul {$first}.",
            '/schedule?date='.$date,
            'appointment.view',
            'appointment.digest:'.$date.':'.($onlyUpcoming ? 'today' : 'tomorrow'),
        )) ? 1 : 0;
    }
}
