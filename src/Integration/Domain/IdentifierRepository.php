<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

/**
 * Pemetaan ID lokal ke ID SATUSEHAT (IHS number): pasien dan praktisi (dokter). NIK dokter disimpan terenkripsi hanya di sini.
 */
interface IdentifierRepository
{
    public function patientIhs(string $patientId): ?string;

    public function rememberPatient(string $patientId, string $ihsId): void;

    public function practitionerNik(string $doctorId): ?string;

    public function practitionerIhs(string $doctorId): ?string;

    public function savePractitionerNik(string $actorId, string $doctorId, ?string $nik): void;

    public function rememberPractitioner(string $doctorId, string $ihsId): void;

    /**
     * @return array<string, array{has_nik: bool, ihs_id: string|null}> doctor_id => status
     */
    public function practitionerStatuses(): array;
}
