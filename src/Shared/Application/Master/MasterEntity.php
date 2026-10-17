<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master;

/**
 * Master data berbentuk daftar yang dapat dikelola lewat halaman pengaturan generik (kursi, tindakan, metode
 * pembayaran, obat). Setiap konteks mendaftarkan entitasnya sendiri; halaman, validasi, dan audit memakai
 * satu mekanisme. Data tidak pernah dihapus (M5): nonaktifkan, riwayat tetap utuh.
 */
interface MasterEntity
{
    /** Slug URL, contoh `chairs`. */
    public function key(): string;

    public function title(): string;

    public function viewPermission(): string;

    public function managePermission(): string;

    /**
     * @return list<MasterField>
     */
    public function fields(): array;

    /**
     * Kunci kolom yang ditampilkan di tabel.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Aturan validasi Laravel per kolom.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(?string $ignoreId): array;

    /**
     * @return list<array<string, mixed>> baris dengan `id`, kolom formulir, dan `is_active`
     */
    public function list(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $actorId, array $data): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $actorId, string $id, array $data): void;

    public function setActive(string $actorId, string $id, bool $active): void;
}
