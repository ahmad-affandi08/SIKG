<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Src\IdentityAccess\Application\Roles\RoleAdministration;
use Src\IdentityAccess\Domain\DefaultRolePermissions;
use Src\IdentityAccess\Domain\Permission;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Domain\UnknownPermission;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

final readonly class EloquentRoleAdministration implements RoleAdministration
{
    public function __construct(private Transaction $tx, private AuditLogger $audit, private Clock $clock) {}

    public function overview(string $actorId): array
    {
        $actor = User::query()->findOrFail($actorId);
        $roles = [];
        foreach (Role::cases() as $role) {
            $model = $this->model($role);
            $roles[] = [
                'value' => $role->value,
                'label' => $role->label(),
                'permissions' => $this->current($model),
                'defaults' => $this->defaults($role),
                'customized' => $model->getAttribute('customized_at') !== null,
                'users' => User::query()->where('is_active', true)->role($role->value)->count(),
                'editable' => $this->mayEdit($actor, $role),
                'locked' => $role === Role::Pemilik ? array_map(fn (Permission $p) => $p->value, Permission::ownerProtected()) : [],
            ];
        }

        $modules = [];
        foreach (Permission::cases() as $permission) {
            $modules[$permission->module()][] = ['value' => $permission->value, 'label' => $permission->label()];
        }

        $grouped = [];
        foreach ($modules as $name => $permissions) {
            $grouped[] = ['name' => $name, 'permissions' => $permissions];
        }

        return ['roles' => $roles, 'modules' => $grouped];
    }

    public function update(string $actorId, string $role, array $permissions): void
    {
        $actor = User::query()->findOrFail($actorId);
        $target = Role::from($role);
        $this->guard($actor, $target);

        $known = array_map(fn (Permission $p) => $p->value, Permission::cases());
        foreach ($permissions as $name) {
            if (! in_array($name, $known, true)) {
                throw new UnknownPermission($name);
            }
        }
        $wanted = $permissions;
        if ($target === Role::Pemilik) {
            $wanted = array_merge($wanted, array_map(fn (Permission $p) => $p->value, Permission::ownerProtected()));
        }
        $wanted = $this->sorted($wanted);

        $this->tx->run(function () use ($actor, $target, $wanted): void {
            $model = $this->model($target);
            $before = $this->current($model);
            if ($before === $wanted) {
                return;
            }

            $model->syncPermissions($wanted);
            $matchesDefault = $wanted === $this->defaults($target);
            $this->markCustomized($model, ! $matchesDefault);
            $this->audit->record(new AuditEntry($actor->id, 'role.permissions_changed', 'role', $target->value, ['permissions' => $before], [
                'permissions' => $wanted,
                'added' => array_values(array_diff($wanted, $before)),
                'removed' => array_values(array_diff($before, $wanted)),
            ]));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function reset(string $actorId, string $role): void
    {
        $actor = User::query()->findOrFail($actorId);
        $target = Role::from($role);
        $this->guard($actor, $target);

        $this->tx->run(function () use ($actor, $target): void {
            $model = $this->model($target);
            $before = $this->current($model);
            $defaults = $this->defaults($target);
            $model->syncPermissions($defaults);
            $this->markCustomized($model, false);
            $this->audit->record(new AuditEntry($actor->id, 'role.permissions_reset', 'role', $target->value, ['permissions' => $before], ['permissions' => $defaults]));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function guard(User $actor, Role $target): void
    {
        if (! $actor->can(Permission::RoleManage->value) || ! $this->mayEdit($actor, $target)) {
            throw new AuthorizationException('Anda tidak berhak mengubah peran ini.');
        }
    }

    /** Hanya Pemilik yang mengubah peran Pemilik (selaras UserPolicy). */
    private function mayEdit(User $actor, Role $target): bool
    {
        return $target !== Role::Pemilik || $actor->hasRole(Role::Pemilik->value);
    }

    private function model(Role $role): RoleModel
    {
        return RoleModel::query()->where('name', $role->value)->where('guard_name', 'web')->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function current(RoleModel $model): array
    {
        return $this->sorted($model->permissions()->pluck('name')->all());
    }

    /**
     * @return list<string>
     */
    private function defaults(Role $role): array
    {
        return $this->sorted(array_map(fn (Permission $p) => $p->value, DefaultRolePermissions::for($role)));
    }

    /**
     * @param  array<int, mixed>  $names
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        $names = array_values(array_unique(array_map('strval', $names)));
        sort($names);

        return $names;
    }

    private function markCustomized(RoleModel $model, bool $customized): void
    {
        DB::table((string) config('permission.table_names.roles'))->where('id', $model->getKey())->update([
            'customized_at' => $customized ? $this->clock->now()->format('Y-m-d H:i:s') : null,
        ]);
    }
}
