<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

interface StockCountRepository
{
    /**
     * @return array{enabled: bool, interval_days: int, next_due_date: string|null}
     */
    public function schedule(): array;

    public function saveSchedule(?string $actorId, bool $enabled, int $intervalDays, ?string $nextDueDate): void;

    public function openSession(): ?StockCountSession;

    /**
     * Membuat sesi terbuka berisi baris untuk tiap obat aktif dengan saldo sistem saat ini.
     */
    public function createSession(?string $actorId, string $scheduledFor, string $source, ?string $note): StockCountSession;

    public function lockSession(string $id): ?StockCountSession;

    public function findSession(string $id): ?StockCountSession;

    /**
     * @return list<StockCountSession> terbaru lebih dulu
     */
    public function recentSessions(int $limit): array;

    /**
     * @return list<StockCountLine>
     */
    public function lines(string $sessionId): array;

    public function recordCount(string $sessionId, string $lineId, ?int $counted): void;

    public function closeSession(string $actorId, string $id, string $status, DateTimeImmutable $at): void;
}
