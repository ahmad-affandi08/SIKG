<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

/**
 * Penyimpanan rencana perawatan (RM-06). Langkah dan rencana dihapus lunak (M5); status rencana disimpan sebagai hasil turunan
 * dari status langkah oleh pemanggil.
 */
interface TreatmentPlanRepository
{
    public function create(string $actorId, string $patientId, string $title, ?string $notes, DateTimeImmutable $at): string;

    public function find(string $planId): ?TreatmentPlanRecord;

    /**
     * Rencana milik pasien, terbaru lebih dulu, beserta langkahnya.
     *
     * @return list<TreatmentPlanRecord>
     */
    public function forPatient(string $patientId): array;

    public function addItem(string $actorId, string $planId, string $procedureId, string $code, string $name, ?int $toothFdi, int $quantity, int $unitPriceAmount, int $estimateAmount, ?string $notes): string;

    public function findItem(string $itemId): ?PlanItemRecord;

    public function setItemStatus(string $actorId, string $itemId, PlanItemStatus $status, ?string $encounterId, ?DateTimeImmutable $completedAt): void;

    /** Soft delete langkah. */
    public function removeItem(string $actorId, string $itemId): void;

    public function setStatus(string $actorId, string $planId, PlanStatus $status): void;

    /**
     * Langkah rencana pasien yang masih terbuka (Direncanakan atau Berjalan) pada rencana yang tidak dibatalkan.
     *
     * @return list<PlanItemRecord>
     */
    public function openItemsForPatient(string $patientId): array;
}
