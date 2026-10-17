<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Shared\Application\SequenceGenerator;

/**
 * Nomor berurut dari tabel `sequences` dengan SELECT ... FOR UPDATE. Kunci bertahan sampai transaksi pemanggil
 * selesai sehingga dua proses tidak pernah menerima nomor yang sama (ADR-0004).
 */
final class DatabaseSequenceGenerator implements SequenceGenerator
{
    public function next(string $name, string $scope = ''): int
    {
        return DB::transaction(function () use ($name, $scope): int {
            // Baris dibuat bila belum ada; insertOrIgnore aman terhadap dua proses yang membuat bersamaan.
            DB::table('sequences')->insertOrIgnore(['name' => $name, 'scope' => $scope, 'next_value' => 0]);

            $row = DB::table('sequences')->where('name', $name)->where('scope', $scope)->lockForUpdate()->first();
            if ($row === null) {
                throw new RuntimeException('Baris sequences tidak ditemukan setelah dibuat');
            }

            $value = (int) $row->next_value + 1;
            DB::table('sequences')->where('name', $name)->where('scope', $scope)->update(['next_value' => $value]);

            return $value;
        });
    }
}
