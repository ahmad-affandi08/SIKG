<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

interface StockRepository
{
    /**
     * Saldo obat dengan baris dikunci selama transaksi pemanggil (dibuat bernilai 0 bila belum ada).
     */
    public function lockedBalance(string $medicineId): int;

    /**
     * Mencatat mutasi bertanda dan memperbarui saldo.
     *
     * @return int saldo sesudah mutasi
     */
    public function apply(
        string $actorId,
        string $medicineId,
        MovementType $type,
        int $delta,
        ?string $referenceType,
        ?string $referenceId,
        ?string $reason,
        ?string $batchNo,
        ?string $expiryDate,
        DateTimeImmutable $at,
    ): int;

    /**
     * @return list<StockMovementRecord> terbaru lebih dulu
     */
    public function movements(string $medicineId, int $limit): array;

    /**
     * Saldo tiap obat aktif untuk daftar stok.
     *
     * @return list<array{id: string, name: string, unit: string, quantity: int, min_stock: int, is_active: bool}>
     */
    public function overview(): array;
}
