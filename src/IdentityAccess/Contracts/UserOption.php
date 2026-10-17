<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Contracts;

final readonly class UserOption
{
    public function __construct(public string $id, public string $name) {}
}
