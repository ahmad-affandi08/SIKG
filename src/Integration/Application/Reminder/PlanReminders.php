<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use DateInterval;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinical\Contracts\RecallLookup;
use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderRepository;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Scheduling\Contracts\UpcomingAppointments;
use Src\Shared\Application\Clock;

/**
 * P2-03: menyiapkan pengingat yang jatuh tempo. Janji besok (H-1) mulai pukul 09.00 hari sebelumnya, janji hari ini (hari-H) mulai
 * pukul 07.00 dan hanya sebelum janji dimulai, dan ajakan kontrol berkala untuk kunjungan terakhir pasien yang ditutup sekitar N bulan lalu
 * (jendela 30 hari agar kunjungan lama tidak membanjiri antrean saat fitur baru dihidupkan). Idempoten: satu pengingat per janji atau
 * kunjungan dan jenis. Pasien yang tidak mau diingatkan dilewati. Hanya membuat baris; pengiriman dilakukan terpisah.
 */
final readonly class PlanReminders
{
    private const DAY_BEFORE_FROM_HOUR = 9;

    private const SAME_DAY_FROM_HOUR = 7;

    private const RECALL_WINDOW_DAYS = 30;

    public function __construct(
        private ReminderSettingsRepository $settings,
        private ReminderRepository $reminders,
        private UpcomingAppointments $appointments,
        private RecallLookup $recalls,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return int jumlah pengingat baru
     */
    public function run(): int
    {
        $settings = $this->settings->get();
        if (! $settings->enabled) {
            return 0;
        }

        $now = $this->clock->now();
        $zone = new DateTimeZone($this->calendar->timezone());
        $local = $now->setTimezone($zone);
        $created = 0;

        if ($settings->dayBefore || $settings->sameDay) {
            $until = $local->setTime(0, 0)->modify('+2 days')->setTimezone(new DateTimeZone('UTC'));
            $infos = $this->appointments->between($now, $until);
            $optedOut = $this->reminders->optedOut(array_values(array_unique(array_map(fn ($a) => $a->patientId, $infos))));
            $hour = (int) $local->format('G');
            $today = $local->format('Y-m-d');
            $tomorrow = $local->modify('+1 day')->format('Y-m-d');

            foreach ($infos as $info) {
                if (isset($optedOut[$info->patientId])) {
                    continue;
                }
                $day = $info->startsAt->setTimezone($zone)->format('Y-m-d');
                $kind = match (true) {
                    $day === $today && $hour >= self::SAME_DAY_FROM_HOUR && $settings->sameDay => ReminderKind::SameDay,
                    $day === $tomorrow && $hour >= self::DAY_BEFORE_FROM_HOUR && $settings->dayBefore => ReminderKind::DayBefore,
                    default => null,
                };
                if ($kind !== null && $this->reminders->enqueue($kind, $info->patientId, $info->id, null, $now)) {
                    $created++;
                }
            }
        }

        if ($settings->recall) {
            $to = $now->sub(new DateInterval('P'.$settings->recallMonths.'M'));
            $from = $to->sub(new DateInterval('P'.self::RECALL_WINDOW_DAYS.'D'));
            $candidates = $this->recalls->latestClosedBetween($from, $to);
            $patientIds = array_values(array_unique(array_map(fn ($c) => $c->patientId, $candidates)));
            $optedOut = $this->reminders->optedOut($patientIds);
            $booked = array_flip($this->appointments->patientsWithAppointmentAfter($patientIds, $now));

            foreach ($candidates as $candidate) {
                if (isset($optedOut[$candidate->patientId]) || isset($booked[$candidate->patientId])) {
                    continue;
                }
                if ($this->reminders->enqueue(ReminderKind::Recall, $candidate->patientId, null, $candidate->encounterId, $now)) {
                    $created++;
                }
            }
        }

        return $created;
    }
}
