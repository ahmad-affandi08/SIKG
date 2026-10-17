<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\TwoFactor;

use Illuminate\Support\Facades\Hash;
use Src\IdentityAccess\Domain\CurrentPasswordIncorrect;
use Src\IdentityAccess\Domain\TwoFactor\RecoveryCodes;
use Src\IdentityAccess\Domain\TwoFactor\Totp;
use Src\IdentityAccess\Domain\TwoFactor\TwoFactorFailed;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * AUTH-05: Pemilik dan Admin mengaktifkan, menonaktifkan, dan memperbarui kode pemulihan verifikasi dua langkah miliknya.
 * Setiap perubahan meminta kata sandi dan kode yang sah; audit mencatat kejadian tanpa rahasia atau kode.
 */
final readonly class TwoFactorSetup
{
    public function __construct(
        private TwoFactorStore $store,
        private TwoFactorVerifier $verifier,
        private AuditLogger $audit,
        private Transaction $tx,
        private Clock $clock,
        private string $issuer,
    ) {}

    /**
     * @return array{enabled: bool, pending: bool, recovery_remaining: int, secret: string|null, uri: string|null}
     */
    public function status(string $userId): array
    {
        $enabled = $this->store->isEnabled($userId);
        $pending = $enabled ? null : $this->store->pendingSecret($userId);

        return [
            'enabled' => $enabled,
            'pending' => $pending !== null,
            'recovery_remaining' => $enabled ? $this->store->recoveryRemaining($userId) : 0,
            'secret' => $pending === null ? null : trim(chunk_split($pending, 4, ' ')),
            'uri' => $pending === null ? null : Totp::uri($pending, $this->store->accountLabel($userId), $this->issuer),
        ];
    }

    public function begin(string $userId): void
    {
        if ($this->store->isEnabled($userId)) {
            throw TwoFactorFailed::alreadyEnabled();
        }

        $this->tx->run(function () use ($userId): void {
            $this->store->putPending($userId, Totp::newSecret());
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_setup_started', 'user', $userId));
        });
    }

    /**
     * @return list<string> kode pemulihan, tampil sekali ini saja
     *
     * @throws CurrentPasswordIncorrect
     * @throws TwoFactorFailed
     */
    public function confirm(string $userId, string $password, string $code): array
    {
        $this->assertPassword($userId, $password);
        $secret = $this->store->pendingSecret($userId) ?? throw TwoFactorFailed::notPending();
        $step = Totp::verify($secret, preg_replace('/\s+/', '', $code) ?? '', $this->clock->now()->getTimestamp());
        if ($step === null) {
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_failed', 'user', $userId, null, ['stage' => 'setup']));

            throw TwoFactorFailed::invalidCode();
        }

        return $this->tx->run(function () use ($userId, $secret, $step): array {
            $codes = RecoveryCodes::generate();
            $this->store->activate($userId, $secret, $this->hashes($codes), $step, $this->clock->now());
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_enabled', 'user', $userId));

            return $codes;
        });
    }

    /**
     * @throws CurrentPasswordIncorrect
     * @throws TwoFactorFailed
     */
    public function disable(string $userId, string $password, string $code): void
    {
        $this->authorizeChange($userId, $password, $code);

        $this->tx->run(function () use ($userId): void {
            $this->store->disable($userId);
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_disabled', 'user', $userId));
        });
    }

    /**
     * @return list<string> kode pemulihan baru; kode lama tidak berlaku lagi
     *
     * @throws CurrentPasswordIncorrect
     * @throws TwoFactorFailed
     */
    public function regenerateRecovery(string $userId, string $password, string $code): array
    {
        $this->authorizeChange($userId, $password, $code);

        return $this->tx->run(function () use ($userId): array {
            $codes = RecoveryCodes::generate();
            $this->store->replaceRecovery($userId, $this->hashes($codes));
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_recovery_regenerated', 'user', $userId));

            return $codes;
        });
    }

    private function authorizeChange(string $userId, string $password, string $code): void
    {
        if (! $this->store->isEnabled($userId)) {
            throw TwoFactorFailed::notEnabled();
        }
        $this->assertPassword($userId, $password);
        if ($this->verifier->check($userId, $code) === null) {
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_failed', 'user', $userId, null, ['stage' => 'change']));

            throw TwoFactorFailed::invalidCode();
        }
    }

    private function assertPassword(string $userId, string $password): void
    {
        if (! Hash::check($password, $this->store->passwordHash($userId))) {
            $this->audit->record(new AuditEntry($userId, 'auth.two_factor_failed', 'user', $userId, null, ['stage' => 'password']));

            throw new CurrentPasswordIncorrect;
        }
    }

    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashes(array $codes): array
    {
        return array_map(fn (string $code) => $this->verifier->recoveryHash($code), $codes);
    }
}
