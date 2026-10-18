<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

use Src\Shared\Application\Master\ImportableMaster;

/**
 * Port validasi satu baris dengan aturan formulir entitas (aturan yang sama dengan menyimpan lewat halaman).
 */
interface MasterRowValidator
{
    /**
     * @param  array<string, string|null>  $values  sudah dinormalkan
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    public function validate(ImportableMaster $entity, array $values): array;
}
