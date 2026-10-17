<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

final class OwnerRoleRestricted extends DomainException
{
    public function __construct()
    {
        parent::__construct('user.owner_role_restricted', 'Hanya Pemilik yang dapat memberikan peran Pemilik.');
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
