<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Integration\Domain\Reminder\ReminderMessage;
use Src\Integration\Domain\Reminder\ReminderRecord;
use Src\Integration\Domain\Reminder\ReminderSettings;
use Src\Patient\Contracts\PatientPhone;
use Src\Scheduling\Contracts\AppointmentLookup;
use Src\Shared\Application\Clock;

/**
 * P2-03: merakit nilai penanda dan pesan untuk satu pengingat dari data terkini (janji, dokter, klinik). Mengembalikan null bila
 * pengingat sudah tidak relevan, yaitu janji sudah dibatalkan, selesai, atau lewat.
 */
final readonly class ReminderContent
{
    public function __construct(
        private AppointmentLookup $appointments,
        private DoctorDirectory $doctors,
        private LetterheadProvider $letterhead,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{values: array<string, string>, message: string, parameters: list<string>}|null
     */
    public function build(ReminderRecord $reminder, PatientPhone $patient, ReminderSettings $settings): ?array
    {
        $values = ['nama' => $this->firstName($patient->name), 'klinik' => $this->letterhead->forDocument()->clinicName];

        if ($reminder->kind->isAppointment()) {
            $info = $reminder->appointmentId === null ? null : $this->appointments->find($reminder->appointmentId);
            if ($info === null || ! in_array($info->status, ['scheduled', 'confirmed'], true) || $info->startsAt <= $this->clock->now()) {
                return null;
            }
            $start = $info->startsAt->setTimezone(new DateTimeZone($this->calendar->timezone()));
            $values['tanggal'] = $start->format('d/m/Y');
            $values['jam'] = $start->format('H:i');
            $values['dokter'] = $this->doctors->byIds([$info->doctorId])[$info->doctorId]->name ?? 'dokter kami';
        } else {
            $values['bulan'] = (string) $settings->recallMonths;
        }

        return [
            'values' => $values,
            'message' => ReminderMessage::render($settings->template($reminder->kind), $values),
            'parameters' => array_map(fn (string $key) => $values[$key] ?? '', $reminder->kind->apiParameters()),
        ];
    }

    /** Nama panggilan: kata pertama nama pasien. */
    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? $name;
    }
}
