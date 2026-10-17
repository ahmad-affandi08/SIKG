<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Klinik harus selalu punya minimal satu Pemilik aktif agar tidak terkunci dari pengelolaan pengguna.
 */
final class LastOwnerProtected extends DomainException
{
    public function __construct()
    {
        parent::__construct('user.last_owner', 'Klinik harus memiliki minimal satu Pemilik aktif.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
