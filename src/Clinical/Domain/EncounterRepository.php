<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Penyimpanan kunjungan beserta catatan, diagnosa, dan tindakannya. Catatan klinis hanya bertambah (versi baru
 * menunjuk versi lama); diagnosa dan tindakan dihapus dengan soft delete (M5).
 */
interface EncounterRepository
{
    public function open(?string $actorId, string $patientId, ?string $appointmentId, string $doctorId, ?string $chairId, DateTimeImmutable $startedAt): string;

    public function find(string $id): ?EncounterRecord;

    public function findForUpdate(string $id): ?EncounterRecord;

    public function findByAppointment(string $appointmentId): ?EncounterRecord;

    /**
     * Kunjungan pasien, terbaru lebih dulu.
     *
     * @return list<EncounterRecord>
     */
    public function forPatient(string $patientId): array;

    public function saveAnamnesis(string $actorId, string $id, ?string $chiefComplaint, ?string $complaintHistory, Vitals $vitals, DateTimeImmutable $at): void;

    public function close(string $actorId, string $id, DateTimeImmutable $at): void;

    public function addNote(string $authorId, string $encounterId, NoteType $type, ?NoteSection $section, string $body, DateTimeImmutable $at, ?string $supersedesId = null, ?string $reason = null): string;

    /**
     * Catatan terkini: versi yang belum digantikan, ditambah seluruh addendum.
     *
     * @return list<NoteRecord>
     */
    public function currentNotes(string $encounterId): array;

    /**
     * Seluruh versi catatan (riwayat), terlama lebih dulu.
     *
     * @return list<NoteRecord>
     */
    public function noteHistory(string $encounterId): array;

    public function findNote(string $encounterId, string $noteId): ?NoteRecord;

    public function addDiagnosis(string $actorId, string $encounterId, string $diagnosisId, string $code, string $name, ?int $toothFdi, ?string $notes): string;

    /**
     * @return list<EncounterDiagnosisRecord>
     */
    public function diagnoses(string $encounterId): array;

    public function findDiagnosis(string $encounterId, string $id): ?EncounterDiagnosisRecord;

    public function removeDiagnosis(string $actorId, string $encounterId, string $id): void;

    public function addProcedure(
        string $actorId,
        string $encounterId,
        string $procedureId,
        string $code,
        string $name,
        string $scope,
        ProcedureTarget $target,
        int $quantity,
        int $unitPriceAmount,
        ?string $notes,
        DateTimeImmutable $at,
        ?string $planItemId = null,
        ?string $resultConditionCode = null,
    ): string;

    /**
     * @return list<EncounterProcedureRecord>
     */
    public function procedures(string $encounterId): array;

    public function findProcedure(string $encounterId, string $id): ?EncounterProcedureRecord;

    public function removeProcedure(string $actorId, string $encounterId, string $id): void;

    /** ODO-05: mencatat keputusan dokter atas usulan pembaruan odontogram (`applied` atau `dismissed`). */
    public function setProcedureOdontogramStatus(string $actorId, string $encounterId, string $id, string $status): void;
}
