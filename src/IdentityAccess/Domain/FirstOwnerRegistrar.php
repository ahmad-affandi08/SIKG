<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

/**
 * Menyiapkan peran dan izin bawaan serta membuat pengguna Pemilik pertama sebuah klinik.
 */
interface FirstOwnerRegistrar
{
    /** @return string ID pengguna Pemilik */
    public function register(string $clinicId, string $name, string $username, string $email, string $temporaryPassword): string;
}
