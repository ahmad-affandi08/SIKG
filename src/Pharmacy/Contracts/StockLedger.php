<?php

declare(strict_types=1);

namespace Src\Pharmacy\Contracts;

/**
 * Kontrak stok bagi konteks lain (Billing menjual obat langsung dari tagihan). Semua pemanggilan harus di dalam transaksi
 * pemanggil agar mutasi stok dan penagihan berhasil atau gagal bersama.
 */
interface StockLedger
{
    /**
     * Mengeluarkan stok. Stok tidak boleh menjadi negatif; `$override` hanya dipakai pemegang izin override (Admin) dan
     * mencatat penyesuaian bertanda sebesar kekurangan sebelum stok keluar. Bila stok kurang dan tanpa override, dilempar
     * galat domain dengan kode `stock.insufficient` (HTTP 409).
     */
    public function issue(string $actorId, string $medicineId, int $quantity, string $referenceType, string $referenceId, ?string $overrideReason = null): void;

    /** Mengembalikan stok yang pernah keluar (pembatalan resep atau tagihan). */
    public function restore(string $actorId, string $medicineId, int $quantity, string $referenceType, string $referenceId, string $reason): void;

    public function available(string $medicineId): int;
}
