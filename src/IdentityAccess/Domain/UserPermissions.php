<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

interface UserPermissions
{
    /**
     * Izin pengguna saat ini (dari peran dan penyesuaian AUTH-07).
     *
     * @return list<string>
     */
    public function of(string $userId): array;
}
