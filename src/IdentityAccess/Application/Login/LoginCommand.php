<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Login;

final readonly class LoginCommand
{
    public function __construct(
        public string $identifier,
        public string $password,
        public string $ip,
    ) {}
}
