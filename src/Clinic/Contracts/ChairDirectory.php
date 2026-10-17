<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

interface ChairDirectory
{
    public function find(string $chairId): ?ChairSummary;

    /**
     * @return list<ChairSummary> kursi aktif, urut nama
     */
    public function active(): array;

    /**
     * @param  list<string>  $ids
     * @return array<string, ChairSummary> termasuk kursi nonaktif
     */
    public function byIds(array $ids): array;
}
