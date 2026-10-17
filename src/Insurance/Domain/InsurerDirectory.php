<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

/**
 * Baca master penjamin (dikelola lewat halaman master data). Penjamin nonaktif tetap terbaca agar klaim lama tampil utuh.
 */
interface InsurerDirectory
{
    public function find(string $id): ?InsurerRecord;

    /**
     * @param  list<string>  $ids
     * @return array<string, InsurerRecord>
     */
    public function byIds(array $ids): array;

    /**
     * @return list<InsurerRecord> hanya yang aktif, urut nama
     */
    public function active(): array;
}
