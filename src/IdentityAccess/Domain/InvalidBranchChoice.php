<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Pilihan cabang aktif yang tidak sah: pengguna terikat satu cabang, atau cabang tidak ditemukan atau nonaktif (P3-04).
 */
final class InvalidBranchChoice extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }
}
