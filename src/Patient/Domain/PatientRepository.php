<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use DateTimeImmutable;

/**
 * Penyimpanan pasien. Implementasi Infrastructure mengenkripsi NIK dan nomor HP serta membuat blind index.
 * Pemanggil membungkus operasi dalam transaksi dan mencatat audit.
 */
interface PatientRepository
{
    public function add(string $actorId, string $medicalRecordNo, PatientProfile $profile): string;

    /**
     * @throws PatientNotFound
     */
    public function update(string $actorId, string $id, PatientProfile $profile): void;

    public function find(string $id): ?PatientRecord;

    /**
     * Soft delete (M5): data tetap ada untuk keperluan hukum dan dapat dipulihkan pengembang.
     *
     * @throws PatientNotFound
     */
    public function archive(string $actorId, string $id): void;

    /**
     * Pencarian nama, nomor RM, NIK, atau nomor HP.
     *
     * @return list<PatientSummary>
     */
    public function search(string $term, int $limit): array;

    /**
     * @return list<DuplicateCandidate>
     */
    public function findDuplicates(PatientProfile $profile, ?string $ignoreId = null): array;

    public function addHistory(string $actorId, string $patientId, MedicalHistoryEntry $entry): string;

    /**
     * @throws PatientNotFound
     */
    public function removeHistory(string $actorId, string $patientId, string $entryId): MedicalHistoryEntry;

    /**
     * @return list<HistoryRecord>
     */
    public function histories(string $patientId): array;

    public function recordConsent(string $actorId, string $patientId, string $version, DateTimeImmutable $givenAt): void;

    /**
     * @return list<ConsentRecord>
     */
    public function consents(string $patientId): array;
}
