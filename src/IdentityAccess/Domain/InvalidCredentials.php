<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Pesan sengaja sama untuk akun tidak ada, kata sandi salah, dan akun nonaktif agar tidak membocorkan keberadaan akun.
 */
final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct('auth.invalid_credentials', 'Nama pengguna atau kata sandi salah.');
    }
}
