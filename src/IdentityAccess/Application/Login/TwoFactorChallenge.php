<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Login;

use Illuminate\Cache\RateLimiter;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorVerifier;
use Src\IdentityAccess\Domain\InvalidCredentials;
use Src\IdentityAccess\Domain\LoginCandidate;
use Src\IdentityAccess\Domain\TooManyLoginAttempts;
use Src\IdentityAccess\Domain\TwoFactor\TwoFactorFailed;
use Src\IdentityAccess\Domain\UserDirectory;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;

/**
 * AUTH-05: langkah kedua masuk setelah kata sandi benar. Percobaan salah dibatasi 5 kali per menit per akun dan IP; kode yang
 * benar menyelesaikan masuk (catatan masuk terakhir dan audit) dan mengembalikan ID pengguna. Presentation memulai sesi.
 */
final readonly class TwoFactorChallenge
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private RateLimiter $limiter,
        private TwoFactorVerifier $verifier,
        private UserDirectory $users,
        private AuditLogger $audit,
        private TenantContext $tenant,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    /**
     * @throws TooManyLoginAttempts
     * @throws TwoFactorFailed
     * @throws InvalidCredentials
     */
    public function __invoke(string $userId, string $code, string $ip): string
    {
        $key = 'two-factor:'.hash('sha256', $userId).'|'.$ip;
        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttempts($this->limiter->availableIn($key));
        }

        $candidate = $this->users->findLoginCandidateById($userId);
        if (! $candidate instanceof LoginCandidate || ! $candidate->isActive) {
            throw new InvalidCredentials;
        }

        $method = $this->verifier->check($userId, $code);
        if ($method === null) {
            $this->limiter->hit($key, self::DECAY_SECONDS);
            $this->auditAs($candidate, 'auth.two_factor_failed', ['stage' => 'login']);

            throw TwoFactorFailed::invalidCode();
        }

        $this->limiter->clear($key);
        $this->tx->run(function () use ($candidate, $method): void {
            $this->users->recordLogin($candidate->id, $this->clock->now());
            $this->auditAs($candidate, 'auth.login', ['two_factor' => $method]);
        });

        return $candidate->id;
    }

    /**
     * @param  non-empty-string  $action
     * @param  array<string, mixed>  $detail
     */
    private function auditAs(LoginCandidate $user, string $action, array $detail): void
    {
        $this->tenant->runAs($user->clinicId, $user->branchId, function () use ($user, $action, $detail): void {
            $this->audit->record(new AuditEntry($user->id, $action, 'user', $user->id, null, $detail));
        });
    }
}
