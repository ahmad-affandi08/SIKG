<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Login;

/**
 * Hasil pemeriksaan kata sandi. Bila `needsSecondFactor`, sesi belum boleh dimulai sampai kode verifikasi dua langkah sah.
 */
final readonly class LoginOutcome
{
    public function __construct(public string $userId, public bool $needsSecondFactor) {}
}
