<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

final class CurrentPasswordIncorrect extends DomainException
{
    public function __construct()
    {
        parent::__construct('auth.current_password_incorrect', 'Kata sandi saat ini salah.');
    }
}
