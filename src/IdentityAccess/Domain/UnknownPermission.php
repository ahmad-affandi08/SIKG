<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use Src\Shared\Domain\DomainException;

final class UnknownPermission extends DomainException
{
    public function __construct(string $name)
    {
        parent::__construct('role.unknown_permission', 'Izin tidak dikenal: '.$name.'.');
    }
}
