<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use Src\IdentityAccess\Contracts\UserLookup;
use Src\IdentityAccess\Contracts\UserOption;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

final class EloquentUserLookup implements UserLookup
{
    public function activeDoctors(): array
    {
        return array_values(User::query()
            ->where('is_active', true)
            ->role(Role::Dokter->value)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => new UserOption($u->id, $u->name))
            ->all());
    }

    public function isActiveDoctor(string $userId): bool
    {
        return User::query()->whereKey($userId)->where('is_active', true)->role(Role::Dokter->value)->exists();
    }
}
