<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Users;

/**
 * Kata sandi sementara yang ditampilkan SEKALI kepada admin. Tidak boleh disimpan di sesi, log, atau audit.
 */
final readonly class UserCredential
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $username,
        public string $temporaryPassword,
    ) {}
}
