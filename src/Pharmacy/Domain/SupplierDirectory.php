<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

interface SupplierDirectory
{
    public function isActive(string $supplierId): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => nama
     */
    public function names(array $ids): array;

    /**
     * Pemasok aktif untuk pilihan.
     *
     * @return list<array{value: string, label: string}>
     */
    public function options(): array;
}
