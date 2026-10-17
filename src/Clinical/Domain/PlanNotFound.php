<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use Src\Shared\Domain\DomainException;

final class PlanNotFound extends DomainException
{
    public function __construct(string $message = 'Rencana perawatan tidak ditemukan.')
    {
        parent::__construct('plan.not_found', $message);
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
