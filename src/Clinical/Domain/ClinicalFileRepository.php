<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

interface ClinicalFileRepository
{
    public function add(string $actorId, string $patientId, string $encounterId, ?int $toothFdi, string $path, string $mime, int $size, FileKind $kind, bool $encrypted, ?string $caption, DateTimeImmutable $at): string;

    public function find(string $id): ?ClinicalFileRecord;

    /**
     * @return list<ClinicalFileRecord>
     */
    public function forEncounter(string $encounterId): array;

    /**
     * @param  list<string>  $encounterIds
     * @return array<string, list<ClinicalFileRecord>> id kunjungan => berkas
     */
    public function forEncounters(array $encounterIds): array;

    /** Soft delete (M5): baris ditandai terhapus, berkas di disk tetap ada untuk keperluan hukum. */
    public function remove(string $actorId, string $id): void;
}
