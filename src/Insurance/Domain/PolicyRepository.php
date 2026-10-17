<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

interface PolicyRepository
{
    public function find(string $id): ?PolicyRecord;

    /**
     * @return list<PolicyRecord> aktif dulu, lalu terbaru
     */
    public function forPatient(string $patientId): array;

    public function add(string $actorId, string $patientId, string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): string;

    public function update(string $actorId, string $id, string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): void;

    public function setActive(string $actorId, string $id, bool $active): void;

    /**
     * Jumlah yang sudah dipakai terhadap plafon: dasar semua klaim yang masih dihitung (draft, diajukan, disetujui, lunas).
     */
    public function committedAmount(string $policyId, ?string $exceptClaimId = null): int;
}
