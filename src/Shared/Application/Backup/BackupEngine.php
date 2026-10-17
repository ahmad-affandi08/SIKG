<?php

declare(strict_types=1);

namespace Src\Shared\Application\Backup;

use DateTimeImmutable;

/**
 * Mesin cadangan dalam PHP murni (tanpa mysqldump atau proses sistem, M21): cadangan data terenkripsi, uji pemulihan,
 * pemulihan ke basis data kosong, dan salinan berkas unggahan. Implementasi di Infrastructure.
 */
interface BackupEngine
{
    /**
     * Membuat cadangan data terenkripsi dan menyimpannya di disk cadangan.
     *
     * @return array{name: string, size: int, tables: int, rows: int, checksum: string}
     */
    public function backup(DateTimeImmutable $at): array;

    /**
     * Uji pemulihan: membongkar cadangan, memulihkannya ke tabel sementara, lalu membandingkan jumlah baris dan
     * checksum tiap tabel dengan manifest. Tidak mengubah data aplikasi.
     *
     * @return array{name: string, tables: int, rows: int, checksum: string}
     */
    public function verify(string $name): array;

    /**
     * Memulihkan ke basis data yang skemanya sudah dimigrasi dan seluruh tabel datanya kosong. Menolak bila ada isi.
     *
     * @return array{name: string, tables: int, rows: int}
     */
    public function restore(string $name): array;

    /**
     * Menyalin berkas unggahan yang belum ada atau berubah ke disk cadangan (cermin; tidak ada yang dihapus).
     * Mengembalikan jumlah berkas di sumber dan yang disalin sekarang, serta berkas sumber yang tidak ada/berbeda di cermin sesudahnya.
     *
     * @return array{files: int, copied: int, missing: int}
     */
    public function mirrorFiles(): array;

    /**
     * @return array<string, DateTimeImmutable> nama => waktu pembuatan (UTC)
     */
    public function backups(): array;

    public function remove(string $name): void;
}
