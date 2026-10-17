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
     * Mencatat mutasi bertanda dan memperbarui saldo serta sisa per batch (P2-05): mutasi positif membuat batch baru
     * (dengan harga beli dan pemasok bila ada), mutasi negatif memakai batch yang paling cepat kedaluwarsa lebih dulu (FEFO;
     * batch tanpa tanggal kedaluwarsa terakhir).
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
        ?int $unitCostAmount = null,
        ?string $supplierId = null,
        ?string $purchaseOrderId = null,
    ): int;

    /**
     * Jumlah bersih yang keluar untuk satu rujukan (keluar dikurangi masuk kembali), tidak pernah negatif.
     */
    public function netIssued(string $medicineId, string $referenceType, string $referenceId): int;

    /**
     * Obat yang pernah bermutasi untuk satu rujukan.
     *
     * @return list<string>
     */
    public function referencedMedicines(string $referenceType, string $referenceId): array;

    /**
     * Batch dengan sisa untuk halaman batch dan kedaluwarsa, kedaluwarsa terdekat lebih dulu.
     *
     * @return list<array{id: string, medicine_id: string, name: string, unit: string, batch_no: string|null, expiry_date: string|null, received_quantity: int, remaining_quantity: int, unit_cost_amount: int|null, supplier_id: string|null, received_at: string}>
     */
    public function batches(?string $medicineId): array;

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

    /**
     * Batch yang masih bersisa dengan tanggal kedaluwarsa paling lambat `$until` (YYYY-MM-DD, termasuk yang sudah lewat).
     * `received` adalah jumlah saat batch masuk dan `quantity` sisa batch itu.
     *
     * @return list<array{medicine_id: string, name: string, unit: string, batch_no: string|null, expiry_date: string, received: int, quantity: int}>
     */
    public function expiringBatches(string $until): array;
}
