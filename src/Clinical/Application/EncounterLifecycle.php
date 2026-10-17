<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Clinical\Domain\EncounterRepository;
use Src\Clinical\Domain\Events\EncounterClosed;
use Src\Clinical\Domain\InvalidClinicalData;
use Src\Scheduling\Contracts\AppointmentLookup;
use Src\Scheduling\Contracts\AppointmentProgress;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Transaction;

/**
 * Mulai pemeriksaan dan tutup kunjungan (RM-07). Status janji di Scheduling mengikuti lewat kontrak AppointmentProgress.
 */
final readonly class EncounterLifecycle
{
    public function __construct(
        private EncounterRepository $encounters,
        private EncounterGuard $guard,
        private AppointmentLookup $appointments,
        private AppointmentProgress $progress,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** Menandai janji Sedang diperiksa; kursi yang dipakai boleh ditetapkan di sini. */
    public function startExamination(string $actorId, string $encounterId, ?string $chairId): void
    {
        $this->tx->run(function () use ($actorId, $encounterId, $chairId): void {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            if ($encounter->appointmentId === null) {
                return;
            }

            $appointment = $this->appointments->find($encounter->appointmentId);
            if ($appointment === null || $appointment->status !== 'checked_in') {
                return;
            }
            $this->progress->markInProgress($actorId, $encounter->appointmentId, $chairId);
            $this->audit->record(new AuditEntry($actorId, 'encounter.examination_started', 'encounter', $encounterId));
        });
    }

    /**
     * Menutup kunjungan: catatan terkunci, janji menjadi Selesai. Setelah ini perubahan hanya lewat addendum atau koreksi
     * dalam 2x24 jam oleh pengguna berwenang.
     */
    public function close(string $actorId, string $encounterId): void
    {
        $event = $this->tx->run(function () use ($actorId, $encounterId): EncounterClosed {
            $encounter = $this->guard->load($encounterId);
            $this->guard->assertOpen($encounter);
            if ($this->encounters->diagnoses($encounterId) === [] && $this->encounters->procedures($encounterId) === [] && $this->encounters->currentNotes($encounterId) === []) {
                throw new InvalidClinicalData('encounter.empty', 'Kunjungan belum memiliki catatan, diagnosa, atau tindakan.');
            }

            $now = $this->clock->now();
            $this->encounters->close($actorId, $encounterId, $now);

            if ($encounter->appointmentId !== null) {
                $appointment = $this->appointments->find($encounter->appointmentId);
                if ($appointment !== null && $appointment->status === 'checked_in') {
                    $this->progress->markInProgress($actorId, $encounter->appointmentId, null);
                }
                if ($appointment !== null && in_array($appointment->status, ['checked_in', 'in_progress'], true)) {
                    $this->progress->markCompleted($actorId, $encounter->appointmentId);
                }
            }

            $this->audit->record(new AuditEntry($actorId, 'encounter.closed', 'encounter', $encounterId, ['status' => 'open'], ['status' => 'closed']));

            return new EncounterClosed($encounterId, $encounter->patientId, $now);
        });

        $this->events->dispatch($event);
    }
}
