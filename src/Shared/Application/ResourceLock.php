<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Kunci eksklusif atas sumber daya logis (contoh dokter, kursi, pasien pada satu hari) selama transaksi berjalan.
 * Dipakai untuk mencegah dua proses memeriksa lalu menulis jadwal yang sama bersamaan. Memakai kunci baris MySQL
 * (tanpa Redis, ADR-0006). Kunci diambil berurutan menurut nama sehingga dua proses tidak saling menunggu (deadlock).
 * Harus dipanggil di dalam transaksi pemakainya; kunci lepas saat transaksi selesai.
 */
interface ResourceLock
{
    /**
     * @param  non-empty-string  ...$keys  contoh `doctor:01J...`, `chair:01J...`
     */
    public function acquire(string ...$keys): void;
}
