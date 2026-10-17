<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Contracts;

/**
 * Kontrak untuk konteks lain yang perlu menautkan sesuatu ke akun pengguna (contoh dokter) tanpa memakai modelnya.
 */
interface UserLookup
{
    /**
     * Pengguna aktif berperan Dokter pada klinik saat ini.
     *
     * @return list<UserOption>
     */
    public function activeDoctors(): array;

    public function isActiveDoctor(string $userId): bool;
}
