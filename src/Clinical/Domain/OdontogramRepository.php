<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use DateTimeImmutable;

interface OdontogramRepository
{
    /** Snapshot kunjungan itu, dibuat bila belum ada (satu per kunjungan). */
    public function snapshotFor(string $actorId, string $patientId, string $encounterId, DateTimeImmutable $at): string;

    /**
     * Menetapkan kondisi (membuat atau memperbarui baris unik per snapshot, gigi, dan permukaan).
     *
     * @return string|null kode kondisi sebelumnya pada pasangan itu di snapshot yang sama, atau null bila baru
     */
    public function setState(string $actorId, string $snapshotId, int $toothFdi, Surface $surface, string $conditionCode, DateTimeImmutable $at): ?string;

    /** Memperbarui dentisi snapshot sesuai gigi yang tercatat. */
    public function refreshDentition(string $snapshotId): void;

    /**
     * Seluruh entri pasien dari semua snapshot.
     *
     * @return list<ToothStateEntry>
     */
    public function entriesForPatient(string $patientId): array;

    /**
     * Entri yang dicatat pada kunjungan itu saja.
     *
     * @return list<ToothStateEntry>
     */
    public function entriesForEncounter(string $encounterId): array;
}
