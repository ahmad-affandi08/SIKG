<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Stok tidak boleh negatif (aturan integritas basis data). Override hanya oleh Admin dengan alasan, tercatat sebagai penyesuaian.
 */
final class InsufficientStock extends DomainException
{
    public function __construct(public readonly int $available, public readonly int $requested)
    {
        parent::__construct('stock.insufficient', "Stok tidak cukup: tersedia {$available}, diminta {$requested}.");
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
