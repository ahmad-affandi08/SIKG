<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\MedicalHistoryEntry;
use Src\Patient\Domain\MedicalHistoryType;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRepository;
use Src\Patient\Domain\Sex;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * PAS-04: riwayat medis (alergi, penyakit sistemik, obat rutin, kehamilan). Perubahan tercatat di audit; penghapusan
 * berupa soft delete sehingga riwayat tetap dapat ditelusuri.
 */
final readonly class ManageMedicalHistory
{
    public function __construct(private PatientRepository $patients, private Transaction $tx, private AuditLogger $audit) {}

    public function add(string $actorId, string $patientId, MedicalHistoryEntry $entry): string
    {
        return $this->tx->run(function () use ($actorId, $patientId, $entry): string {
            $patient = $this->patients->find($patientId) ?? throw new PatientNotFound;
            if ($entry->type === MedicalHistoryType::Pregnancy && $patient->profile->sex !== Sex::Female) {
                throw new InvalidPatientData('patient.invalid_history', 'Riwayat kehamilan hanya untuk pasien perempuan.');
            }

            $id = $this->patients->addHistory($actorId, $patientId, $entry);
            $this->audit->record(new AuditEntry($actorId, 'patient.history_added', 'patient', $patientId, null, $this->view($id, $entry)));

            return $id;
        });
    }

    public function remove(string $actorId, string $patientId, string $entryId): void
    {
        $this->tx->run(function () use ($actorId, $patientId, $entryId): void {
            $this->patients->find($patientId) ?? throw new PatientNotFound;
            $entry = $this->patients->removeHistory($actorId, $patientId, $entryId);

            $this->audit->record(new AuditEntry($actorId, 'patient.history_removed', 'patient', $patientId, $this->view($entryId, $entry), null));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function view(string $id, MedicalHistoryEntry $entry): array
    {
        return ['id' => $id, 'type' => $entry->type->value, 'name' => $entry->name, 'severity' => $entry->severity?->value];
    }
}
