<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Users;

use Src\IdentityAccess\Domain\Role;

final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $username,
        public Role $role,
        public ?string $branchId,
    ) {}
}
