<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Shared\Application\ResourceLock;
use Src\Shared\Application\TenantContext;

/**
 * Kunci lewat baris di tabel `sequences` (nama `lock`, lingkup = klinik + kunci). Baris dibuat bila belum ada lalu
 * dikunci dengan SELECT ... FOR UPDATE; nilainya tidak dipakai.
 */
final readonly class DatabaseResourceLock implements ResourceLock
{
    public function __construct(private TenantContext $tenant) {}

    public function acquire(string ...$keys): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Kunci sumber daya harus diambil di dalam transaksi.');
        }
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');

        $scopes = array_map(fn (string $key) => "{$clinic}:{$key}", $keys);
        sort($scopes);

        foreach (array_unique($scopes) as $scope) {
            DB::table('sequences')->insertOrIgnore(['name' => 'lock', 'scope' => $scope, 'next_value' => 0]);
            DB::table('sequences')->where('name', 'lock')->where('scope', $scope)->lockForUpdate()->first();
        }
    }
}
