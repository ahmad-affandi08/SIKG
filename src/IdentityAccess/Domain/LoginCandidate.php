<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain;

/**
 * Data minimum pengguna yang diperlukan untuk memeriksa login. Tanpa PHI.
 */
final readonly class LoginCandidate
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public ?string $branchId,
        public string $passwordHash,
        public bool $isActive,
    ) {}
}
