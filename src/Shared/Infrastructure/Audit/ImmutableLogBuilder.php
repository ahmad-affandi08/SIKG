<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Builder untuk tabel log: tidak ada UPDATE atau DELETE lewat aplikasi (M10), termasuk operasi massal.
 *
 * @extends Builder<Model>
 */
final class ImmutableLogBuilder extends Builder
{
    public function update(array $values): int
    {
        throw self::refuse();
    }

    public function delete(): mixed
    {
        throw self::refuse();
    }

    /**
     * @param  array<int, array<string, mixed>>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int, string>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): int
    {
        throw self::refuse();
    }

    private static function refuse(): LogicException
    {
        return new LogicException('Log audit hanya boleh di-INSERT; ubah dan hapus dilarang.');
    }
}
