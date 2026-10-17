<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure\Auth;

use DateTimeImmutable;
use Src\IdentityAccess\Domain\LoginCandidate;
use Src\IdentityAccess\Domain\UserDirectory;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

final class EloquentUserDirectory implements UserDirectory
{
    public function findLoginCandidate(string $identifier): ?LoginCandidate
    {
        $user = User::query()
            ->withoutGlobalScope(ClinicScope::class)
            ->where(fn ($query) => $query->where('email', $identifier)->orWhere('username', $identifier))
            ->first();

        return $user instanceof User ? $this->candidate($user) : null;
    }

    public function findLoginCandidateById(string $userId): ?LoginCandidate
    {
        $user = User::query()->withoutGlobalScope(ClinicScope::class)->find($userId);

        return $user instanceof User ? $this->candidate($user) : null;
    }

    public function recordLogin(string $userId, DateTimeImmutable $at): void
    {
        User::query()->withoutGlobalScope(ClinicScope::class)->whereKey($userId)->update(['last_login_at' => $at]);
    }

    private function candidate(User $user): LoginCandidate
    {
        return new LoginCandidate(
            id: $user->id,
            clinicId: $user->clinic_id,
            branchId: $user->branch_id,
            passwordHash: (string) $user->getAuthPassword(),
            isActive: $user->is_active,
        );
    }
}
