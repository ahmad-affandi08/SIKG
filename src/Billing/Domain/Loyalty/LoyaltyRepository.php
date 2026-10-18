<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

interface LoyaltyRepository
{
    public function settings(): LoyaltySettings;

    public function saveSettings(string $actorId, LoyaltySettings $settings): void;

    /**
     * Saldo poin pasien; pasien dikunci selama transaksi pemanggil agar dua penukaran bersamaan tidak melebihi saldo.
     */
    public function lockedBalance(string $patientId): int;

    /**
     * Saldo poin pasien tanpa mengunci (tampilan).
     */
    public function balance(string $patientId): int;

    /**
     * Menambah satu baris buku poin (hanya-tambah). `dedupeKey` yang sama tidak menambah dua kali.
     *
     * @return bool false bila baris dengan kunci itu sudah ada
     */
    public function append(string $patientId, int $delta, string $kind, ?string $invoiceId, ?string $dedupeKey, ?string $note, ?string $actorId): bool;

    /**
     * @return list<array{delta: int, balance_after: int, kind: string, note: string|null, created_at: string}> terbaru lebih dulu
     */
    public function ledger(string $patientId, int $limit): array;

    /**
     * Poin yang sudah didapat dari tagihan ini (positif) dan belum dibalik.
     */
    public function earnedFor(string $invoiceId): int;
}
