<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use Src\Shared\Domain\DomainException;

final class EncounterNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('encounter.not_found', 'Kunjungan tidak ditemukan.');
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
