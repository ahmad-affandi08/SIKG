<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use Src\IdentityAccess\Domain\UserPermissions;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

final class EloquentUserPermissions implements UserPermissions
{
    public function of(string $userId): array
    {
        $names = [];
        foreach (User::query()->findOrFail($userId)->getAllPermissions() as $permission) {
            $names[] = (string) $permission->name;
        }

        return $names;
    }
}
