<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Users;

/**
 * AUTH-03: kelola pengguna. Port Application untuk konteks CRUD terstruktur (ADR-0001); implementasi dengan Eloquent
 * ada di Infrastructure sehingga Presentation dan Application tetap bebas dari Eloquent. Setiap metode memeriksa
 * Policy untuk pelaku (`$actorId`) dan melempar AuthorizationException bila ditolak (M9).
 */
interface UserAdministration
{
    /**
     * @return list<array<string, mixed>> baris daftar, termasuk `canManage` per pengguna
     */
    public function list(string $actorId): array;

    public function canCreate(string $actorId): bool;

    /**
     * @return array<string, mixed> data formulir ubah, termasuk `canManage`
     */
    public function find(string $actorId, string $userId): array;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function assignableRoles(string $actorId): array;

    /**
     * @return list<array{value: string, label: string}> cabang aktif untuk pilihan
     */
    public function branchOptions(): array;

    public function create(string $actorId, UserData $data): UserCredential;

    public function update(string $actorId, string $userId, UserData $data): void;

    public function deactivate(string $actorId, string $userId): void;

    public function activate(string $actorId, string $userId): void;

    public function resetPassword(string $actorId, string $userId): UserCredential;

    /**
     * AUTH-05: mematikan verifikasi dua langkah pengguna lain yang kehilangan perangkat dan kode pemulihannya. Sesinya berakhir.
     */
    public function resetTwoFactor(string $actorId, string $userId): void;
}
