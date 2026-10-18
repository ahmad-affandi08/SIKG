<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

/**
 * Penyimpanan sementara baris sah antara pratinjau dan konfirmasi (data master, bukan data pasien). Kedaluwarsa sendiri.
 */
interface MasterImportStore
{
    /**
     * @param  list<array{row: int, data: array<string, mixed>}>  $rows
     * @param  array<string, mixed>  $summary  ringkasan pratinjau untuk ditampilkan ulang
     */
    public function put(string $token, string $actorId, string $entityKey, array $rows, array $summary): void;

    /**
     * @return array<string, mixed>|null
     */
    public function summary(string $token, string $actorId, string $entityKey): ?array;

    /**
     * @return list<array{row: int, data: array<string, mixed>}>|null null bila tidak ada, kedaluwarsa, atau bukan milik pemanggil
     */
    public function get(string $token, string $actorId, string $entityKey): ?array;

    /**
     * Mengklaim token untuk satu konfirmasi saja secara atomik; false bila sudah diklaim permintaan lain.
     */
    public function claim(string $token): bool;

    /** Melepas klaim agar konfirmasi dapat diulang (dipakai bila pemrosesan gagal). */
    public function release(string $token): void;

    public function forget(string $token): void;
}
