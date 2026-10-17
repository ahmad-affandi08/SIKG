<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

final class PasswordUnchanged extends DomainException
{
    public function __construct()
    {
        parent::__construct('auth.password_unchanged', 'Kata sandi baru harus berbeda dari kata sandi saat ini.');
    }
}
