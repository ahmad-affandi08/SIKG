<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

final class TooManyLoginAttempts extends DomainException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('auth.too_many_attempts', "Terlalu banyak percobaan masuk. Coba lagi dalam {$retryAfterSeconds} detik.");
    }

    public function httpStatus(): int
    {
        return 429;
    }
}
