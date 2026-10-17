<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * Mengarsipkan pasien (soft delete, M5). Riwayat kunjungan dan tagihan tetap utuh.
 */
final readonly class ArchivePatient
{
    public function __construct(private PatientRepository $patients, private Transaction $tx, private AuditLogger $audit) {}

    public function __invoke(string $actorId, string $patientId): void
    {
        $this->tx->run(function () use ($actorId, $patientId): void {
            $current = $this->patients->find($patientId) ?? throw new PatientNotFound;
            $this->patients->archive($actorId, $patientId);

            $this->audit->record(new AuditEntry($actorId, 'patient.archived', 'patient', $patientId, ['medical_record_no' => $current->medicalRecordNo], null));
        });
    }
}
