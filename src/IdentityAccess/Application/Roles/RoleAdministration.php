<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Roles;

/**
 * AUTH-07: Pemilik dan Admin menyesuaikan izin tiap peran tanpa mengubah kode (PRD 00 bagian 4, Q-8). Port Application untuk
 * konteks CRUD (ADR-0001). Aturan: hanya Pemilik yang mengubah peran Pemilik, peran Pemilik tidak pernah kehilangan izin kelola
 * pengguna dan peran, dan setiap perubahan tercatat di audit log.
 */
interface RoleAdministration
{
    /**
     * @return array{
     *     roles: list<array{value: string, label: string, permissions: list<string>, defaults: list<string>, customized: bool, users: int, editable: bool, locked: list<string>}>,
     *     modules: list<array{name: string, permissions: list<array{value: string, label: string}>}>
     * }
     */
    public function overview(string $actorId): array;

    /**
     * @param  list<string>  $permissions  nama izin yang dimiliki peran setelah perubahan
     */
    public function update(string $actorId, string $role, array $permissions): void;

    /** Mengembalikan izin peran ke matriks bawaan. */
    public function reset(string $actorId, string $role): void;
}
