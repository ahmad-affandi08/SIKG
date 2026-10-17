<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Src\Clinic\Contracts\BranchDirectory;
use Src\IdentityAccess\Application\Users\UserAdministration;
use Src\IdentityAccess\Application\Users\UserCredential;
use Src\IdentityAccess\Application\Users\UserData;
use Src\IdentityAccess\Domain\LastOwnerProtected;
use Src\IdentityAccess\Domain\OwnerRoleRestricted;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * Pengguna tidak pernah dihapus (M5): "hapus" berarti nonaktifkan dan riwayat tetap utuh. Aturan bisnis:
 * peran Pemilik hanya diberikan Pemilik, dan klinik selalu punya minimal satu Pemilik aktif.
 */
final readonly class EloquentUserAdministration implements UserAdministration
{
    public function __construct(
        private Transaction $tx,
        private AuditLogger $audit,
        private BranchDirectory $branches,
    ) {}

    public function list(string $actorId): array
    {
        $actor = $this->actor($actorId);
        $branchNames = collect($this->branches->all())->mapWithKeys(fn ($b) => [$b->id => $b->name]);

        return array_values(User::query()->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'username' => $u->username,
            'role' => $u->getRoleNames()->first(),
            'branch' => $u->branch_id !== null ? $branchNames->get($u->branch_id) : null,
            'isActive' => $u->is_active,
            'twoFactorEnabled' => $u->two_factor_confirmed_at !== null,
            'lastLoginAt' => $u->last_login_at?->toIso8601String(),
            'canManage' => Gate::forUser($actor)->allows('update', $u),
        ])->all());
    }

    public function canCreate(string $actorId): bool
    {
        return Gate::forUser($this->actor($actorId))->allows('create', User::class);
    }

    public function find(string $actorId, string $userId): array
    {
        $actor = $this->actor($actorId);
        $target = User::query()->findOrFail($userId);
        Gate::forUser($actor)->authorize('view', $target);

        return [
            'id' => $target->id,
            'name' => $target->name,
            'email' => $target->email,
            'username' => $target->username,
            'role' => $target->getRoleNames()->first(),
            'branch_id' => $target->branch_id,
            'isActive' => $target->is_active,
            'twoFactorEnabled' => $target->two_factor_confirmed_at !== null,
            'canManage' => Gate::forUser($actor)->allows('update', $target),
        ];
    }

    public function assignableRoles(string $actorId): array
    {
        $canGiveOwner = $this->actor($actorId)->hasRole(Role::Pemilik->value);

        return array_values(collect(Role::cases())
            ->filter(fn (Role $r) => $r !== Role::Pemilik || $canGiveOwner)
            ->map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()])
            ->all());
    }

    public function branchOptions(): array
    {
        return array_values(array_map(
            fn ($b) => ['value' => $b->id, 'label' => $b->name],
            array_filter($this->branches->all(), fn ($b) => $b->active),
        ));
    }

    public function create(string $actorId, UserData $data): UserCredential
    {
        $actor = $this->actor($actorId);
        Gate::forUser($actor)->authorize('create', User::class);
        $this->guardRoleAssignment($actor, $data->role);
        $password = $this->generatePassword();

        $user = $this->tx->run(function () use ($actor, $data, $password): User {
            $user = new User;
            $user->forceFill([
                'clinic_id' => $actor->clinic_id,
                'branch_id' => $data->branchId,
                'name' => $data->name,
                'email' => $data->email,
                'username' => $data->username,
                'password' => $password,
                'must_change_password' => true,
                'is_active' => true,
                'created_by' => $actor->id,
            ])->save();
            $user->syncRoles([$data->role->value]);

            $this->audit->record(new AuditEntry($actor->id, 'user.created', 'user', $user->id, null, $this->snapshot($user, $data->role->value)));

            return $user;
        });

        return new UserCredential($user->id, $user->name, $user->username, $password);
    }

    public function update(string $actorId, string $userId, UserData $data): void
    {
        $actor = $this->actor($actorId);
        $target = User::query()->findOrFail($userId);
        Gate::forUser($actor)->authorize('update', $target);
        $this->guardRoleAssignment($actor, $data->role);

        $this->tx->run(function () use ($actor, $target, $data): void {
            $currentRole = $this->roleOf($target);
            if ($currentRole === Role::Pemilik->value && $data->role !== Role::Pemilik) {
                $this->guardNotLastOwner($target);
            }

            $before = $this->snapshot($target, $currentRole);
            $target->forceFill([
                'name' => $data->name,
                'email' => $data->email,
                'username' => $data->username,
                'branch_id' => $data->branchId,
                'updated_by' => $actor->id,
            ])->save();
            $target->syncRoles([$data->role->value]);

            $this->audit->record(new AuditEntry($actor->id, 'user.updated', 'user', $target->id, $before, $this->snapshot($target, $data->role->value)));
        });
    }

    public function deactivate(string $actorId, string $userId): void
    {
        $actor = $this->actor($actorId);
        $target = User::query()->findOrFail($userId);
        Gate::forUser($actor)->authorize('delete', $target);

        $this->tx->run(function () use ($actor, $target): void {
            if ($target->hasRole(Role::Pemilik->value)) {
                $this->guardNotLastOwner($target);
            }

            $target->forceFill(['is_active' => false, 'updated_by' => $actor->id])->save();
            $this->audit->record(new AuditEntry($actor->id, 'user.deactivated', 'user', $target->id, ['is_active' => true], ['is_active' => false]));
        });
    }

    public function activate(string $actorId, string $userId): void
    {
        $actor = $this->actor($actorId);
        $target = User::query()->findOrFail($userId);
        Gate::forUser($actor)->authorize('update', $target);

        $this->tx->run(function () use ($actor, $target): void {
            $target->forceFill(['is_active' => true, 'updated_by' => $actor->id])->save();
            $this->audit->record(new AuditEntry($actor->id, 'user.activated', 'user', $target->id, ['is_active' => false], ['is_active' => true]));
        });
    }

    public function resetPassword(string $actorId, string $userId): UserCredential
    {
        $actor = $this->actor($actorId);
        $target = User::query()->findOrFail($userId);
        Gate::forUser($actor)->authorize('update', $target);
        $password = $this->generatePassword();

        $this->tx->run(function () use ($actor, $target, $password): void {
            $target->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => null, 'updated_by' => $actor->id])->save();
            // Sesi lama pengguna itu berakhir; ia harus masuk dengan kata sandi sementara lalu menggantinya (AUTH-06).
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
            $this->audit->record(new AuditEntry($actor->id, 'user.password_reset', 'user', $target->id));
        });

        return new UserCredential($target->id, $target->name, $target->username, $password);
    }

    public function resetTwoFactor(string $actorId, string $userId): void
    {
        $actor = $this->actor($actorId);
        $target = User::query()->findOrFail($userId);
        Gate::forUser($actor)->authorize('update', $target);

        $this->tx->run(function () use ($actor, $target): void {
            $target->forceFill([
                'two_factor_secret_encrypted' => null, 'two_factor_pending_encrypted' => null, 'two_factor_confirmed_at' => null,
                'two_factor_last_step' => 0, 'two_factor_recovery_hashes' => null, 'remember_token' => null, 'updated_by' => $actor->id,
            ])->save();
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
            $this->audit->record(new AuditEntry($actor->id, 'user.two_factor_reset', 'user', $target->id));
        });
    }

    private function actor(string $actorId): User
    {
        return User::query()->findOrFail($actorId);
    }

    private function guardRoleAssignment(User $actor, Role $role): void
    {
        if ($role === Role::Pemilik && ! $actor->hasRole(Role::Pemilik->value)) {
            throw new OwnerRoleRestricted;
        }
    }

    private function guardNotLastOwner(User $target): void
    {
        $owners = User::query()
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->role(Role::Pemilik->value)
            ->count();

        if ($owners === 0) {
            throw new LastOwnerProtected;
        }
    }

    private function roleOf(User $user): ?string
    {
        $role = $user->getRoleNames()->first();

        return is_string($role) ? $role : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user, ?string $role): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'branch_id' => $user->branch_id,
            'role' => $role,
            'is_active' => $user->is_active,
        ];
    }

    private function generatePassword(): string
    {
        return Str::password(14, symbols: false);
    }
}
