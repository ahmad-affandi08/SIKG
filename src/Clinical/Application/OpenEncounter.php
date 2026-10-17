<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Scheduling\Contracts\AppointmentLookup;
use Src\Scheduling\Domain\Events\PatientCheckedIn;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * RM-01: kunjungan dibuat saat check-in; satu kunjungan memiliki satu dokter utama. Idempoten per janji sehingga event
 * ganda atau pemanggilan manual tidak membuat kunjungan kedua.
 */
final readonly class OpenEncounter
{
    public function __construct(
        private EncounterRepository $encounters,
        private AppointmentLookup $appointments,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /** Pendengar event PatientCheckedIn dari Scheduling. */
    public function onCheckIn(PatientCheckedIn $event): void
    {
        $this->open(null, $event->appointmentId, $event->patientId, $event->doctorId, $event->chairId);
    }

    /**
     * Membuat kunjungan dari janji yang sudah check-in (atau mengembalikan yang sudah ada).
     *
     * @return string id kunjungan
     */
    public function forAppointment(string $actorId, string $appointmentId): string
    {
        $appointment = $this->appointments->find($appointmentId)
            ?? throw new InvalidClinicalData('encounter.appointment_not_found', 'Janji tidak ditemukan.');
        if (! in_array($appointment->status, ['checked_in', 'in_progress', 'completed'], true)) {
            throw new InvalidClinicalData('encounter.not_checked_in', 'Kunjungan hanya dapat dimulai setelah pasien check-in.');
        }

        return $this->open($actorId, $appointmentId, $appointment->patientId, $appointment->doctorId, $appointment->chairId);
    }

    private function open(?string $actorId, string $appointmentId, string $patientId, string $doctorId, ?string $chairId): string
    {
        return $this->tx->run(function () use ($actorId, $appointmentId, $patientId, $doctorId, $chairId): string {
            $existing = $this->encounters->findByAppointment($appointmentId);
            if ($existing !== null) {
                return $existing->id;
            }

            $id = $this->encounters->open($actorId, $patientId, $appointmentId, $doctorId, $chairId, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'encounter.opened', 'encounter', $id, null, ['patient_id' => $patientId, 'doctor_id' => $doctorId, 'appointment_id' => $appointmentId]));

            return $id;
        });
    }
}
