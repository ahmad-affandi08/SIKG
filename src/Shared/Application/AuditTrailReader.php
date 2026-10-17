<?php

declare(strict_types=1);

namespace Src\Shared\Application;

use DateTimeImmutable;

/**
 * Baca jejak audit dan log akses untuk halaman audit (SYS-01). Hanya membaca; log tidak pernah diubah atau dihapus (M10).
 * Batas waktu dalam UTC; nama pelaku dan pasien dipetakan oleh pemanggil lewat kontrak konteks masing-masing.
 */
interface AuditTrailReader
{
    /**
     * @param  array{action?: string|null, subject_type?: string|null, subject_id?: string|null, actor_id?: string|null, from?: DateTimeImmutable|null, to?: DateTimeImmutable|null}  $filters  `to` eksklusif
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function entries(array $filters, int $page, int $perPage): array;

    /**
     * @return array<string, mixed>|null
     */
    public function entry(string $id): ?array;

    /**
     * @return list<string>
     */
    public function subjectTypes(): array;

    /**
     * @return list<string> id pelaku yang pernah tercatat
     */
    public function actorIds(): array;

    /**
     * @param  array{actor_id?: string|null, patient_id?: string|null, from?: DateTimeImmutable|null, to?: DateTimeImmutable|null}  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function accesses(array $filters, int $page, int $perPage): array;
}
