<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

use DateTimeImmutable;

/**
 * Port pencarian pengguna untuk login. Pencarian terjadi sebelum ada konteks klinik, jadi implementasinya
 * (Infrastructure) mencari lintas klinik; identitas pengguna unik secara global.
 */
interface UserDirectory
{
    public function findLoginCandidate(string $identifier): ?LoginCandidate;

    public function recordLogin(string $userId, DateTimeImmutable $at): void;
}
