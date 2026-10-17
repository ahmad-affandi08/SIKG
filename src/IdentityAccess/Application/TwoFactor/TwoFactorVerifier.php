<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\TwoFactor;

use Src\IdentityAccess\Domain\TwoFactor\RecoveryCodes;
use Src\IdentityAccess\Domain\TwoFactor\Totp;
use Src\Shared\Application\Clock;

/**
 * AUTH-05: memeriksa kode faktor kedua, yaitu kode 6 digit dari autentikator atau satu kode pemulihan. Satu kode TOTP tidak
 * dapat dipakai dua kali dan kode pemulihan hangus setelah dipakai.
 */
final readonly class TwoFactorVerifier
{
    public function __construct(private TwoFactorStore $store, private Clock $clock, private string $recoveryKey) {}

    /**
     * @return 'totp'|'recovery'|null cara yang berhasil, atau null bila kode salah
     */
    public function check(string $userId, string $input): ?string
    {
        $input = trim($input);
        $secret = $this->store->activeSecret($userId);
        if ($secret === null) {
            return null;
        }

        $digits = preg_replace('/\s+/', '', $input) ?? '';
        if (preg_match('/^\d{'.Totp::DIGITS.'}$/', $digits) === 1) {
            $step = Totp::verify($secret, $digits, $this->clock->now()->getTimestamp(), $this->store->lastStep($userId));

            return $step !== null && $this->store->advanceStep($userId, $step) ? 'totp' : null;
        }

        $recovery = RecoveryCodes::normalize($input);

        return $recovery !== null && $this->store->consumeRecovery($userId, RecoveryCodes::hash($recovery, $this->recoveryKey)) ? 'recovery' : null;
    }

    public function recoveryHash(string $normalizedCode): string
    {
        return RecoveryCodes::hash($normalizedCode, $this->recoveryKey);
    }
}
