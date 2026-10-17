<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure\Auth;

use Src\IdentityAccess\Domain\Permission;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

/**
 * Policy untuk pengguna (AUTH-02, M9). Izin diperiksa di server; menyembunyikan menu di UI bukan keamanan.
 * Aturan matriks: Admin dapat mengelola pengguna tetapi tidak dapat mengubah Pemilik (docs/PRD/00 bagian 4).
 * Bawaan aman: tanpa izin yang tepat, semua aksi ditolak.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UserView->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can(Permission::UserView->value) && $this->sameClinic($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UserManage->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::UserManage->value)
            && $this->sameClinic($actor, $target)
            && $this->mayTouch($actor, $target);
    }

    /**
     * Pengguna tidak pernah dihapus permanen (M5); "hapus" berarti nonaktifkan.
     */
    public function delete(User $actor, User $target): bool
    {
        return $this->update($actor, $target) && $actor->id !== $target->id;
    }

    private function sameClinic(User $actor, User $target): bool
    {
        return $actor->clinic_id === $target->clinic_id;
    }

    /**
     * Hanya Pemilik yang boleh mengubah akun Pemilik.
     */
    private function mayTouch(User $actor, User $target): bool
    {
        return ! $target->hasRole(Role::Pemilik->value) || $actor->hasRole(Role::Pemilik->value);
    }
}
