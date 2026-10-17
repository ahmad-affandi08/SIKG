<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use Src\Clinic\Contracts\ChairDirectory;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Patient\Contracts\PatientLookup;
use Src\Scheduling\Domain\AppointmentConflict;
use Src\Scheduling\Domain\AppointmentNotFound;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\AppointmentStatus;
use Src\Scheduling\Domain\Events\AppointmentBooked;
use Src\Scheduling\Domain\Events\PatientCheckedIn;
use Src\Scheduling\Domain\InvalidAppointment;
use Src\Scheduling\Domain\PracticeHours;
use Src\Scheduling\Domain\TimeSlot;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;

/**
 * JDW-01, JDW-02, JDW-05, JDW-08: membuat janji, pasien walk-in, dan jadwal ulang. Bentrok dicegah dengan kunci baris
 * pada dokter, kursi, dan pasien lalu pemeriksaan tumpang tindih di dalam satu transaksi.
 */
final readonly class AppointmentScheduler
{
    public const WALK_IN_MINUTES = 30;

    public function __construct(
        private AppointmentRepository $appointments,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private ChairDirectory $chairs,
        private ClinicCalendar $calendar,
        private ClinicTime $time,
        private ResourceLock $locks,
        private SequenceGenerator $sequences,
        private TenantContext $tenant,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  string  $date  YYYY-MM-DD lokal klinik
     * @param  string  $time  HH:MM lokal klinik
     * @return string id janji
     */
    public function book(string $actorId, string $patientId, string $doctorId, ?string $chairId, string $date, string $time, int $minutes, ?string $complaint): string
    {
        $id = $this->tx->run(function () use ($actorId, $patientId, $doctorId, $chairId, $date, $time, $minutes, $complaint): string {
            $slot = $this->validatedSlot($patientId, $doctorId, $chairId, $date, $time, $minutes);
            $this->lock($patientId, $doctorId, $chairId);
            $this->assertFree($doctorId, $chairId, $patientId, $slot, null);

            $id = $this->appointments->add($actorId, $patientId, $doctorId, $chairId, $slot, AppointmentStatus::Scheduled, 'booking', $this->clean($complaint));
            $this->audit->record(new AuditEntry($actorId, 'appointment.booked', 'appointment', $id, null, $this->view($patientId, $doctorId, $chairId, $slot)));

            return $id;
        });

        $this->events->dispatch(new AppointmentBooked($id, $this->clock->now()));

        return $id;
    }

    /**
     * Pasien datang tanpa janji: langsung check-in dengan nomor antrean harian. Tidak diperiksa terhadap jadwal praktik
     * atau bentrok karena urutannya ditentukan antrean.
     */
    public function walkIn(string $actorId, string $patientId, string $doctorId, ?string $chairId, ?string $complaint): string
    {
        [$id, $event] = $this->tx->run(function () use ($actorId, $patientId, $doctorId, $chairId, $complaint): array {
            $this->assertParticipants($patientId, $doctorId, $chairId);
            $now = $this->clock->now();
            $slot = TimeSlot::of($now, self::WALK_IN_MINUTES);
            $date = $this->time->today();

            $number = $this->nextQueueNumber($date);
            $id = $this->appointments->add($actorId, $patientId, $doctorId, $chairId, $slot, AppointmentStatus::CheckedIn, 'walkin', $this->clean($complaint), $number, $date, $now);
            $this->audit->record(new AuditEntry($actorId, 'appointment.walk_in', 'appointment', $id, null, $this->view($patientId, $doctorId, $chairId, $slot) + ['queue_no' => $number]));

            return [$id, new PatientCheckedIn($id, $patientId, $doctorId, $chairId, $now)];
        });

        $this->events->dispatch($event);

        return $id;
    }

    /**
     * JDW-08: jadwal ulang. Hanya janji yang belum check-in; alasan wajib dan tercatat di audit.
     */
    public function reschedule(string $actorId, string $appointmentId, string $doctorId, ?string $chairId, string $date, string $time, int $minutes, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidAppointment('appointment.reason_required', 'Alasan jadwal ulang wajib diisi.');
        }

        $this->tx->run(function () use ($actorId, $appointmentId, $doctorId, $chairId, $date, $time, $minutes, $reason): void {
            $current = $this->appointments->find($appointmentId) ?? throw new AppointmentNotFound;
            $this->lock($current->patientId, $doctorId, $chairId, $current->doctorId, $current->chairId);
            $current = $this->appointments->findForUpdate($appointmentId) ?? throw new AppointmentNotFound;

            if (! $current->status->canReschedule()) {
                throw new InvalidAppointment('appointment.cannot_reschedule', 'Hanya janji yang belum check-in yang dapat dijadwalkan ulang.');
            }
            $slot = $this->validatedSlot($current->patientId, $doctorId, $chairId, $date, $time, $minutes);
            $this->assertFree($doctorId, $chairId, $current->patientId, $slot, $appointmentId);

            $this->appointments->reschedule($actorId, $appointmentId, $doctorId, $chairId, $slot, $reason);
            $this->audit->record(new AuditEntry(
                $actorId,
                'appointment.rescheduled',
                'appointment',
                $appointmentId,
                $this->view($current->patientId, $current->doctorId, $current->chairId, $current->slot()),
                $this->view($current->patientId, $doctorId, $chairId, $slot) + ['reason' => $reason],
            ));
        });
    }

    private function validatedSlot(string $patientId, string $doctorId, ?string $chairId, string $date, string $time, int $minutes): TimeSlot
    {
        $this->assertParticipants($patientId, $doctorId, $chairId);

        $slot = TimeSlot::of($this->time->parse($date, $time), $minutes);
        $localStart = $this->time->toLocal($slot->start);
        $localEnd = $this->time->toLocal($slot->end);

        if ($date < $this->time->today()) {
            throw new InvalidAppointment('appointment.in_past', 'Janji tidak dapat dibuat pada tanggal yang sudah lewat.');
        }
        if ($this->calendar->isHoliday($date)) {
            throw new InvalidAppointment('appointment.clinic_closed', 'Klinik libur pada tanggal itu.');
        }
        $hours = new PracticeHours($this->doctors->weeklySchedule($doctorId));
        if (! $hours->covers($localStart, $localEnd)) {
            throw new InvalidAppointment('appointment.outside_schedule', 'Jam itu di luar jadwal praktik dokter.');
        }

        return $slot;
    }

    private function assertParticipants(string $patientId, string $doctorId, ?string $chairId): void
    {
        if (! $this->patients->exists($patientId)) {
            throw new InvalidAppointment('appointment.patient_not_found', 'Pasien tidak ditemukan.');
        }
        $doctor = $this->doctors->find($doctorId);
        if ($doctor === null || ! $doctor->active) {
            throw new InvalidAppointment('appointment.doctor_unavailable', 'Dokter tidak ditemukan atau tidak aktif.');
        }
        if ($chairId !== null) {
            $chair = $this->chairs->find($chairId);
            if ($chair === null || ! $chair->active) {
                throw new InvalidAppointment('appointment.chair_unavailable', 'Kursi tidak ditemukan atau tidak aktif.');
            }
        }
    }

    private function lock(string $patientId, string $doctorId, ?string $chairId, ?string $otherDoctorId = null, ?string $otherChairId = null): void
    {
        $keys = ["patient:{$patientId}", "doctor:{$doctorId}"];
        if ($chairId !== null) {
            $keys[] = "chair:{$chairId}";
        }
        if ($otherDoctorId !== null) {
            $keys[] = "doctor:{$otherDoctorId}";
        }
        if ($otherChairId !== null) {
            $keys[] = "chair:{$otherChairId}";
        }
        $this->locks->acquire(...array_values(array_unique($keys)));
    }

    private function assertFree(string $doctorId, ?string $chairId, string $patientId, TimeSlot $slot, ?string $ignoreId): void
    {
        $conflict = $this->appointments->findConflict($doctorId, $chairId, $patientId, $slot, $ignoreId);
        if ($conflict !== null) {
            /** @var 'doctor'|'chair'|'patient' $conflict */
            throw new AppointmentConflict($conflict);
        }
    }

    private function nextQueueNumber(string $date): int
    {
        $clinic = $this->tenant->clinicId() ?? throw new InvalidAppointment('appointment.no_clinic', 'Konteks klinik kosong.');

        return $this->sequences->next('queue_no', "clinic:{$clinic}:{$date}");
    }

    /**
     * @return array<string, mixed>
     */
    private function view(string $patientId, string $doctorId, ?string $chairId, TimeSlot $slot): array
    {
        return [
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'chair_id' => $chairId,
            'starts_at' => $slot->start->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $slot->end->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private function clean(?string $text): ?string
    {
        return $text !== null && trim($text) !== '' ? trim($text) : null;
    }
}
