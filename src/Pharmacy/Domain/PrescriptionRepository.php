<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

interface PrescriptionRepository
{
    public function add(string $actorId, string $encounterId, string $patientId, string $doctorId, DateTimeImmutable $at, ?string $allergyAcknowledged): string;

    /** @return string id item */
    public function addItem(string $actorId, string $prescriptionId, PrescriptionItemInput $input, string $name, int $unitPriceAmount, ?DateTimeImmutable $dispensedAt): string;

    public function find(string $id): ?PrescriptionRecord;

    public function findForUpdate(string $id): ?PrescriptionRecord;

    /**
     * @return list<PrescriptionRecord> resep kunjungan (termasuk yang dibatalkan), terlama lebih dulu
     */
    public function forEncounter(string $encounterId): array;

    public function void(string $actorId, string $id, string $reason): void;
}
