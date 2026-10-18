<?php

declare(strict_types=1);

namespace Src\Clinical\Application\Imaging;

use DateTimeImmutable;

/**
 * Folder masuk gambar rontgen klinik (P3-03): perangkat lunak sensor atau pesawat rontgen menyimpan berkas di sini (lewat folder jaringan,
 * SFTP, atau unggah), lalu petugas mengaitkannya ke kunjungan. Berkas yang sudah diproses dipindah ke `imported` atau `rejected`, tidak dihapus.
 */
interface InboxStore
{
    /**
     * @return list<array{name: string, size: int, modified: DateTimeImmutable}> berkas gambar yang menunggu, terbaru dulu
     */
    public function pending(): array;

    public function read(string $name): string;

    /** @param  'imported'|'rejected'  $folder */
    public function archive(string $name, string $folder): void;
}
