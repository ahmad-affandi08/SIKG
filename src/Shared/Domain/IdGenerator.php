<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Pembuat ID ULID (CHAR(26)) untuk entity (ADR-0004). Domain memakai antarmuka ini, bukan fungsi global.
 */
interface IdGenerator
{
    /**
     * @return non-empty-string ULID 26 karakter
     */
    public function generate(): string;
}
