<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use Src\IdentityAccess\Domain\FirstOwnerRegistrar;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;

final class EloquentFirstOwnerRegistrar implements FirstOwnerRegistrar
{
    public function register(string $clinicId, string $name, string $username, string $email, string $temporaryPassword): string
    {
        (new RolePermissionSeeder)->run();

        $owner = new User;
        $owner->forceFill([
            'clinic_id' => $clinicId, 'branch_id' => null, 'name' => $name, 'email' => $email, 'username' => $username,
            'password' => $temporaryPassword, 'is_active' => true, 'must_change_password' => true,
        ])->save();
        $owner->assignRole(Role::Pemilik->value);

        return (string) $owner->id;
    }
}
