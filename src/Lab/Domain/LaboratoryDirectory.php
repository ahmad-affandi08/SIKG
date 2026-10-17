<?php

declare(strict_types=1);

namespace Src\Lab\Domain;

interface LaboratoryDirectory
{
    public function isActive(string $laboratoryId): bool;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => nama (termasuk nonaktif)
     */
    public function names(array $ids): array;

    /**
     * @return list<array{value: string, label: string}> laboratorium aktif
     */
    public function options(): array;
}
