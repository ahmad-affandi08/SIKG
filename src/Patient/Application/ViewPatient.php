<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\ConsentRecord;
use Src\Patient\Domain\HistoryRecord;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRecord;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AccessLogger;

/**
 * Membuka rekam pasien: mencatat siapa membuka (M10) lalu mengembalikan data lengkap beserta riwayat dan persetujuan.
 */
final readonly class ViewPatient
{
    public function __construct(private PatientRepository $patients, private AccessLogger $access) {}

    /**
     * @return array{patient: PatientRecord, histories: list<HistoryRecord>, consents: list<ConsentRecord>}
     */
    public function __invoke(string $patientId, string $route): array
    {
        $patient = $this->patients->find($patientId) ?? throw new PatientNotFound;
        $this->access->patientRecordOpened($patientId, $route);

        return [
            'patient' => $patient,
            'histories' => $this->patients->histories($patientId),
            'consents' => $this->patients->consents($patientId),
        ];
    }
}
