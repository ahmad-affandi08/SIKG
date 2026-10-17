<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Src\IdentityAccess\Domain\DefaultRolePermissions;
use Src\IdentityAccess\Domain\Permission;
use Src\IdentityAccess\Domain\Role;

/**
 * Mengisi peran dan izin bawaan dari matriks PRD. Aman dijalankan berulang (tidak menggandakan).
 * Catatan: menyelaraskan ulang izin peran ke bawaan; penyesuaian pemilik klinik dikelola lewat antarmuka peran (AUTH-02).
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        foreach (Role::cases() as $role) {
            $model = RoleModel::findOrCreate($role->value, 'web');
            $model->syncPermissions(array_map(
                static fn (Permission $p): string => $p->value,
                DefaultRolePermissions::for($role),
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
