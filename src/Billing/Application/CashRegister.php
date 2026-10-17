<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use DateTimeImmutable;

/**
 * Catatan uang tunai kasir untuk tutup kas (KSR-08). Hanya pembayaran bermetode tunai yang belum pernah ditutup.
 */
interface CashRegister
{
    /**
     * Bersih penerimaan tunai (pembayaran dan uang muka dikurangi pengembalian) milik satu kasir yang belum ditutup
     * dan terjadi sebelum batas waktu. Bertanda: pengembalian dapat melebihi penerimaan.
     *
     * @return array{amount: int, payments: int}
     */
    public function unclosed(string $cashierId, DateTimeImmutable $before): array;

    /**
     * Menyimpan penutupan dan menautkan pembayaran tunai yang tercakup; setelah ini tidak ikut penutupan lain.
     *
     * @return string id penutupan
     */
    public function close(string $actorId, string $cashierId, string $date, int $systemAmount, int $countedAmount, int $differenceAmount, ?string $note, DateTimeImmutable $at, DateTimeImmutable $before): string;

    /**
     * @return list<array<string, mixed>> terbaru lebih dulu
     */
    public function history(?string $cashierId, int $limit): array;
}
