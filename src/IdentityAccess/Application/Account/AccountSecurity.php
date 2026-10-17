<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Account;

use Src\IdentityAccess\Domain\CurrentPasswordIncorrect;
use Src\IdentityAccess\Domain\PasswordUnchanged;

/**
 * AUTH-06: pengguna mengganti kata sandinya sendiri. Port Application untuk konteks CRUD (ADR-0001); implementasi Eloquent
 * ada di Infrastructure. Mengganti kata sandi mengakhiri sesi lain milik pengguna itu dan membersihkan tanda wajib ganti.
 */
interface AccountSecurity
{
    /** Kata sandi saat ini masih sementara (akun baru atau hasil reset). */
    public function mustChangePassword(string $userId): bool;

    /**
     * @param  string|null  $keepSessionId  sesi yang tetap hidup (sesi yang sedang dipakai)
     *
     * @throws CurrentPasswordIncorrect
     * @throws PasswordUnchanged
     */
    public function changePassword(string $userId, string $current, string $new, ?string $keepSessionId): void;
}
