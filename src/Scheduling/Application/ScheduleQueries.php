<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use DateInterval;
use DateTimeImmutable;
use Src\Clinic\Contracts\ChairDirectory;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Patient\Contracts\PatientLookup;
use Src\Scheduling\Domain\AppointmentRecord;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\InvalidAppointment;
use Src\Scheduling\Domain\PracticeHours;
use Src\Scheduling\Domain\TimeSlot;
use Src\Shared\Application\Clock;

/**
 * JDW-03 dan JDW-06: kalender harian dan mingguan, slot kosong dokter, dan antrean harian. Hanya membaca.
 */
final readonly class ScheduleQueries
{
    public const SLOT_STEP_MINUTES = 15;

    public function __construct(
        private AppointmentRepository $appointments,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private ChairDirectory $chairs,
        private ClinicCalendar $calendar,
        private ClinicTime $time,
        private Clock $clock,
    ) {}

    /**
     * @param  'day'|'week'  $view
     * @return array{from: string, to: string, items: list<array<string, mixed>>}
     */
    public function calendar(string $date, string $view, ?string $doctorId, ?string $chairId): array
    {
        $this->time->parse($date, '00:00');
        $local = new DateTimeImmutable($date, $this->time->zone());
        $from = $view === 'week' ? $local->modify('monday this week') : $local;
        $to = $view === 'week' ? $from->modify('+6 days') : $from;
        [$fromUtc, $toUtc] = $this->time->range($from->format('Y-m-d'), $to->format('Y-m-d'));

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'items' => $this->present($this->appointments->between($fromUtc, $toUtc, $doctorId, $chairId)),
        ];
    }

    /**
     * Antrean harian (tanggal lokal klinik): janji yang sudah memiliki nomor antrean.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(?string $date = null): array
    {
        return $this->present($this->appointments->queue($date ?? $this->time->today()));
    }

    /**
     * Jam mulai yang masih kosong untuk dokter (dan kursi bila diberikan) pada tanggal lokal itu, per 15 menit.
     *
     * @return list<string> HH:MM
     */
    public function availability(string $doctorId, string $date, int $minutes, ?string $chairId = null): array
    {
        if ($this->calendar->isHoliday($date) || $date < $this->time->today()) {
            return [];
        }
        $this->time->parse($date, '00:00');
        $weekday = (int) (new DateTimeImmutable($date, $this->time->zone()))->format('N');
        $hours = new PracticeHours($this->doctors->weeklySchedule($doctorId));
        if ($minutes < TimeSlot::MIN_MINUTES || $minutes > TimeSlot::MAX_MINUTES) {
            throw new InvalidAppointment('appointment.invalid_duration', 'Durasi janji tidak valid.');
        }

        [$fromUtc, $toUtc] = $this->time->range($date, $date);
        $busy = [];
        foreach ($this->appointments->between($fromUtc, $toUtc, $doctorId) as $a) {
            if ($a->status->occupiesSlot()) {
                $busy[] = $a->slot();
            }
        }
        if ($chairId !== null) {
            foreach ($this->appointments->between($fromUtc, $toUtc, null, $chairId) as $a) {
                if ($a->status->occupiesSlot()) {
                    $busy[] = $a->slot();
                }
            }
        }

        $now = $this->clock->now();
        $free = [];
        foreach ($hours->onWeekday($weekday) as $block) {
            $cursor = $this->time->parse($date, $block['start']);
            $blockEnd = $this->time->parse($date, $block['end']);
            while ($cursor->modify("+{$minutes} minutes") <= $blockEnd) {
                $slot = TimeSlot::of($cursor, $minutes);
                if ($slot->start >= $now && ! $this->collides($slot, $busy)) {
                    $free[] = $this->time->toLocal($cursor)->format('H:i');
                }
                $cursor = $cursor->add(new DateInterval('PT'.self::SLOT_STEP_MINUTES.'M'));
            }
        }

        return array_values(array_unique($free));
    }

    /**
     * @param  list<TimeSlot>  $busy
     */
    private function collides(TimeSlot $slot, array $busy): bool
    {
        foreach ($busy as $b) {
            if ($slot->overlaps($b)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<AppointmentRecord>  $records
     * @return list<array<string, mixed>>
     */
    private function present(array $records): array
    {
        $patients = $this->patients->summaries(array_map(fn (AppointmentRecord $r) => $r->patientId, $records));
        $doctors = $this->doctors->byIds(array_map(fn (AppointmentRecord $r) => $r->doctorId, $records));
        $chairs = $this->chairs->byIds(array_values(array_filter(array_map(fn (AppointmentRecord $r) => $r->chairId, $records))));

        return array_map(function (AppointmentRecord $r) use ($patients, $doctors, $chairs): array {
            $start = $this->time->toLocal($r->startsAt);
            $end = $this->time->toLocal($r->endsAt);

            return [
                'id' => $r->id,
                'date' => $start->format('Y-m-d'),
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'minutes' => $r->slot()->minutes(),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'source' => $r->source,
                'queue_no' => $r->queueNo,
                'complaint' => $r->complaint,
                'cancel_reason' => $r->cancelReason,
                'patient_id' => $r->patientId,
                'patient_name' => ($patients[$r->patientId] ?? null)?->name,
                'medical_record_no' => ($patients[$r->patientId] ?? null)?->medicalRecordNo,
                'doctor_id' => $r->doctorId,
                'doctor_name' => ($doctors[$r->doctorId] ?? null)?->name,
                'chair_id' => $r->chairId,
                'chair_name' => $r->chairId === null ? null : (($chairs[$r->chairId] ?? null)?->name),
                'next' => array_map(fn ($s) => $s->value, $r->status->next()),
            ];
        }, $records);
    }
}
