<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\Login;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorStore;
use Src\IdentityAccess\Domain\InvalidCredentials;
use Src\IdentityAccess\Domain\LoginCandidate;
use Src\IdentityAccess\Domain\TooManyLoginAttempts;
use Src\IdentityAccess\Domain\UserDirectory;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;
use Src\Shared\Application\Transaction;

/**
 * AUTH-01: login dengan email atau username. Percobaan gagal dibatasi 5 kali per menit per akun dan IP.
 * AUTH-05: bila pengguna mengaktifkan verifikasi dua langkah, kata sandi yang benar belum memulai sesi; `TwoFactorChallenge`
 * menyelesaikan masuk. Mengembalikan hasilnya; Presentation yang memulai sesi.
 */
final readonly class LoginHandler
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private RateLimiter $limiter,
        private UserDirectory $users,
        private Transaction $tx,
        private Clock $clock,
        private AuditLogger $audit,
        private TenantContext $tenant,
        private TwoFactorStore $twoFactor,
    ) {}

    /**
     * @throws TooManyLoginAttempts
     * @throws InvalidCredentials
     */
    public function __invoke(LoginCommand $command): LoginOutcome
    {
        $key = $this->throttleKey($command);

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyLoginAttempts($this->limiter->availableIn($key));
        }

        $candidate = $this->users->findLoginCandidate($command->identifier);

        // Hash dihitung juga untuk akun yang tidak ada agar waktu respons tidak membocorkan keberadaan akun.
        $hash = $candidate instanceof LoginCandidate ? $candidate->passwordHash : Hash::make('kata-sandi-pengganti');
        $passwordOk = Hash::check($command->password, $hash);

        if (! $candidate instanceof LoginCandidate || ! $passwordOk || ! $candidate->isActive) {
            $this->limiter->hit($key, self::DECAY_SECONDS);
            if ($candidate instanceof LoginCandidate) {
                $this->auditAs($candidate, 'auth.login_failed');
            }

            throw new InvalidCredentials;
        }

        $this->limiter->clear($key);

        if ($this->twoFactor->isEnabled($candidate->id)) {
            $this->auditAs($candidate, 'auth.password_accepted');

            return new LoginOutcome($candidate->id, true);
        }

        $this->tx->run(function () use ($candidate): void {
            $this->users->recordLogin($candidate->id, $this->clock->now());
            $this->auditAs($candidate, 'auth.login');
        });

        return new LoginOutcome($candidate->id, false);
    }

    private function throttleKey(LoginCommand $command): string
    {
        // Pengenal di-hash: kunci cache tidak boleh memuat email atau nama pengguna mentah.
        return 'login:'.hash('sha256', mb_strtolower($command->identifier)).'|'.$command->ip;
    }

    /**
     * @param  non-empty-string  $action
     */
    private function auditAs(LoginCandidate $user, string $action): void
    {
        $this->tenant->runAs($user->clinicId, $user->branchId, function () use ($user, $action): void {
            $this->audit->record(new AuditEntry(
                actorId: $user->id,
                action: $action,
                subjectType: 'user',
                subjectId: $user->id,
            ));
        });
    }
}
