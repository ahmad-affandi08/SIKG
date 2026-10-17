<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\DuplicatePatientSuspected;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * Mengubah data identitas pasien. NIK atau kemiripan nama yang berubah memicu pemeriksaan duplikat yang sama dengan
 * pendaftaran. Sebelum dan sesudah dicatat di audit (NIK dan nomor HP tersamar).
 */
final readonly class UpdatePatient
{
    public function __construct(
        private PatientRepository $patients,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    public function __invoke(string $actorId, string $patientId, PatientDraft $draft, bool $acknowledgeDuplicates = false): void
    {
        $profile = $draft->toProfile($this->clock->now());

        $this->tx->run(function () use ($actorId, $patientId, $profile, $acknowledgeDuplicates): void {
            $current = $this->patients->find($patientId) ?? throw new PatientNotFound;

            if (! $acknowledgeDuplicates && $this->patients->findDuplicates($profile, $patientId) !== []) {
                throw new DuplicatePatientSuspected;
            }

            $this->patients->update($actorId, $patientId, $profile);

            $this->audit->record(new AuditEntry($actorId, 'patient.updated', 'patient', $patientId, $current->profile->auditView(), $profile->auditView()));
        });
    }
}
