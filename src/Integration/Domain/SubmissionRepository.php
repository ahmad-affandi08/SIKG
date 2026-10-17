<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

use DateTimeImmutable;

interface SubmissionRepository
{
    /** Membuat antrean untuk kunjungan bila belum ada. true bila baru dibuat. */
    public function enqueue(string $encounterId, string $patientId, DateTimeImmutable $at): bool;

    public function find(string $id): ?SubmissionRecord;

    /**
     * Pengiriman yang jatuh tempo dicoba, terlama dulu.
     *
     * @return list<SubmissionRecord>
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /**
     * @return list<SubmissionRecord> terbaru lebih dulu
     */
    public function recent(int $limit): array;

    /** @return array<string, int> status => jumlah */
    public function counts(): array;

    public function markSent(string $id, string $encounterIhsId, DateTimeImmutable $at): void;

    /** Gagal sementara: percobaan bertambah dan dijadwalkan ulang, atau gagal bila percobaan habis. */
    public function markRetry(string $id, string $error, ?DateTimeImmutable $nextAttemptAt): void;

    public function markFailed(string $id, string $error): void;

    public function markBlocked(string $id, string $reason): void;

    public function markSkipped(string $id, string $reason): void;

    /** Mengembalikan ke antrean (kirim ulang manual): percobaan diulang dari nol. */
    public function requeue(string $id, DateTimeImmutable $at): void;
}
