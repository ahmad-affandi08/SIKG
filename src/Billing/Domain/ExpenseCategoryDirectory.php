<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

interface ExpenseCategoryDirectory
{
    public function isActive(string $categoryId): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => nama (termasuk nonaktif)
     */
    public function names(array $ids): array;

    /**
     * @return list<array{value: string, label: string}> kategori aktif
     */
    public function options(): array;
}
