<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

use DateTimeImmutable;

interface ReminderRepository
{
    /** Membuat pengingat bila belum ada untuk janji atau kunjungan itu. true bila baru dibuat. */
    public function enqueue(ReminderKind $kind, string $patientId, ?string $appointmentId, ?string $encounterId, DateTimeImmutable $dueAt): bool;

    public function find(string $id): ?ReminderRecord;

    /**
     * @return list<ReminderRecord> yang masih menunggu dan sudah jatuh tempo, terlama dulu
     */
    public function pendingDue(DateTimeImmutable $now, int $limit): array;

    /**
     * @return list<ReminderRecord> riwayat selain menunggu, terbaru dulu
     */
    public function history(int $limit): array;

    public function markSent(string $id, string $channel, ?string $actorId, DateTimeImmutable $at): void;

    /** Gagal kirim: percobaan bertambah; setelah batas percobaan menjadi gagal, selain itu tetap menunggu. */
    public function markAttemptFailed(string $id, string $error, int $maxAttempts): void;

    public function markSkipped(string $id, string $reason): void;

    public function isOptedOut(string $patientId): bool;

    /**
     * @param  list<string>  $patientIds
     * @return array<string, true> patient_id => true bagi yang tidak mau diingatkan
     */
    public function optedOut(array $patientIds): array;

    public function optOut(string $actorId, string $patientId): void;

    public function optIn(string $actorId, string $patientId): void;

    /**
     * @return list<string> pasien yang tidak mau diingatkan
     */
    public function optOutList(): array;
}
