<?php

declare(strict_types=1);

namespace Src\Scheduling\Application;

use Src\Clinic\Contracts\ChairDirectory;
use Src\Scheduling\Contracts\AppointmentProgress;
use Src\Scheduling\Domain\AppointmentNotFound;
use Src\Scheduling\Domain\AppointmentRecord;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\AppointmentStatus;
use Src\Scheduling\Domain\Events\AppointmentMissed;
use Src\Scheduling\Domain\Events\PatientCheckedIn;
use Src\Scheduling\Domain\InvalidAppointment;
use Src\Scheduling\Domain\InvalidTransition;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\SequenceGenerator;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;

/**
 * JDW-04, JDW-05, JDW-08: perubahan status janji. Alur status dijaga oleh AppointmentStatus; setiap perubahan tercatat
 * di audit. Pembatalan wajib beralasan. `start` dan `complete` dipakai konteks Clinical lewat AppointmentProgress.
 */
final readonly class AppointmentWorkflow implements AppointmentProgress
{
    public function __construct(
        private AppointmentRepository $appointments,
        private ChairDirectory $chairs,
        private ClinicTime $time,
        private SequenceGenerator $sequences,
        private TenantContext $tenant,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    public function confirm(string $actorId, string $appointmentId): void
    {
        $this->transition($actorId, $appointmentId, AppointmentStatus::Confirmed, 'appointment.confirmed');
    }

    public function checkIn(string $actorId, string $appointmentId): void
    {
        $event = $this->tx->run(function () use ($actorId, $appointmentId): PatientCheckedIn {
            $current = $this->forTransition($appointmentId, AppointmentStatus::CheckedIn);
            $localDate = $this->time->toLocal($current->startsAt)->format('Y-m-d');
            if ($localDate !== $this->time->today()) {
                throw new InvalidAppointment('appointment.not_today', 'Check-in hanya pada hari janji.');
            }

            $clinic = $this->tenant->clinicId() ?? throw new InvalidAppointment('appointment.no_clinic', 'Konteks klinik kosong.');
            $number = $this->sequences->next('queue_no', "clinic:{$clinic}:{$localDate}");
            $now = $this->clock->now();
            $this->appointments->checkIn($actorId, $appointmentId, $number, $localDate, $now);

            $this->audit->record(new AuditEntry($actorId, 'appointment.checked_in', 'appointment', $appointmentId, ['status' => $current->status->value], ['status' => 'checked_in', 'queue_no' => $number]));

            return new PatientCheckedIn($appointmentId, $current->patientId, $current->doctorId, $current->chairId, $now);
        });

        $this->events->dispatch($event);
    }

    public function cancel(string $actorId, string $appointmentId, string $reason): void
    {
        $this->transition($actorId, $appointmentId, AppointmentStatus::Cancelled, 'appointment.cancelled', $this->requiredReason($reason, 'pembatalan'));
    }

    public function noShow(string $actorId, string $appointmentId): void
    {
        $event = $this->tx->run(function () use ($actorId, $appointmentId): AppointmentMissed {
            $current = $this->forTransition($appointmentId, AppointmentStatus::NoShow);
            if ($this->clock->now() < $current->startsAt) {
                throw new InvalidAppointment('appointment.not_started', 'Tidak datang hanya dapat dicatat setelah jam janji dimulai.');
            }
            $this->appointments->changeStatus($actorId, $appointmentId, AppointmentStatus::NoShow);
            $this->audit->record(new AuditEntry($actorId, 'appointment.no_show', 'appointment', $appointmentId, ['status' => $current->status->value], ['status' => 'no_show']));

            return new AppointmentMissed($appointmentId, $this->clock->now());
        });

        $this->events->dispatch($event);
    }

    public function markInProgress(string $actorId, string $appointmentId, ?string $chairId = null): void
    {
        $this->tx->run(function () use ($actorId, $appointmentId, $chairId): void {
            $current = $this->forTransition($appointmentId, AppointmentStatus::InProgress);
            if ($chairId !== null) {
                $chair = $this->chairs->find($chairId);
                if ($chair === null || ! $chair->active) {
                    throw new InvalidAppointment('appointment.chair_unavailable', 'Kursi tidak ditemukan atau tidak aktif.');
                }
                $this->appointments->assignChair($actorId, $appointmentId, $chairId);
            }
            $this->appointments->changeStatus($actorId, $appointmentId, AppointmentStatus::InProgress);
            $this->audit->record(new AuditEntry($actorId, 'appointment.started', 'appointment', $appointmentId, ['status' => $current->status->value], ['status' => 'in_progress']));
        });
    }

    public function markCompleted(string $actorId, string $appointmentId): void
    {
        $this->transition($actorId, $appointmentId, AppointmentStatus::Completed, 'appointment.completed');
    }

    /**
     * @param  non-empty-string  $action
     */
    private function transition(string $actorId, string $appointmentId, AppointmentStatus $target, string $action, ?string $reason = null): void
    {
        $this->tx->run(function () use ($actorId, $appointmentId, $target, $action, $reason): void {
            $current = $this->forTransition($appointmentId, $target);
            $this->appointments->changeStatus($actorId, $appointmentId, $target, $reason);
            $after = ['status' => $target->value] + ($reason === null ? [] : ['reason' => $reason]);
            $this->audit->record(new AuditEntry($actorId, $action, 'appointment', $appointmentId, ['status' => $current->status->value], $after));
        });
    }

    private function forTransition(string $appointmentId, AppointmentStatus $target): AppointmentRecord
    {
        $current = $this->appointments->findForUpdate($appointmentId) ?? throw new AppointmentNotFound;
        if (! $current->status->canBecome($target)) {
            throw new InvalidTransition($current->status, $target);
        }

        return $current;
    }

    private function requiredReason(string $reason, string $what): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidAppointment('appointment.reason_required', "Alasan {$what} wajib diisi.");
        }

        return $reason;
    }
}
