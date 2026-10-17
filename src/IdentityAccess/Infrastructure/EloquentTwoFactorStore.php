<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorStore;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

/**
 * Penyimpanan faktor kedua pada tabel pengguna. Dipakai juga saat masuk, sebelum ada konteks klinik; ID pengguna unik secara
 * global sehingga pencarian melepas batas klinik seperti pencarian login.
 */
final readonly class EloquentTwoFactorStore implements TwoFactorStore
{
    public function __construct(private PiiProtector $pii) {}

    public function isEnabled(string $userId): bool
    {
        $user = $this->user($userId);

        return $user->two_factor_confirmed_at !== null && $user->two_factor_secret_encrypted !== null;
    }

    public function pendingSecret(string $userId): ?string
    {
        $value = $this->user($userId)->two_factor_pending_encrypted;

        return $value === null ? null : $this->pii->decrypt($value);
    }

    public function putPending(string $userId, string $secret): void
    {
        $this->query($userId)->update(['two_factor_pending_encrypted' => $this->pii->encrypt($secret)]);
    }

    public function activeSecret(string $userId): ?string
    {
        $user = $this->user($userId);

        return $user->two_factor_confirmed_at === null || $user->two_factor_secret_encrypted === null ? null : $this->pii->decrypt($user->two_factor_secret_encrypted);
    }

    public function activate(string $userId, string $secret, array $recoveryHashes, int $step, DateTimeImmutable $at): void
    {
        $this->query($userId)->update([
            'two_factor_secret_encrypted' => $this->pii->encrypt($secret),
            'two_factor_pending_encrypted' => null,
            'two_factor_confirmed_at' => $at,
            'two_factor_last_step' => $step,
            'two_factor_recovery_hashes' => json_encode($recoveryHashes, JSON_THROW_ON_ERROR),
        ]);
    }

    public function disable(string $userId): void
    {
        $this->query($userId)->update([
            'two_factor_secret_encrypted' => null,
            'two_factor_pending_encrypted' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => 0,
            'two_factor_recovery_hashes' => null,
        ]);
    }

    public function replaceRecovery(string $userId, array $recoveryHashes): void
    {
        $this->query($userId)->update(['two_factor_recovery_hashes' => json_encode($recoveryHashes, JSON_THROW_ON_ERROR)]);
    }

    public function recoveryRemaining(string $userId): int
    {
        return count($this->user($userId)->two_factor_recovery_hashes ?? []);
    }

    public function lastStep(string $userId): int
    {
        return (int) $this->user($userId)->two_factor_last_step;
    }

    public function advanceStep(string $userId, int $step): bool
    {
        return $this->query($userId)->where('two_factor_last_step', '<', $step)->update(['two_factor_last_step' => $step]) === 1;
    }

    public function consumeRecovery(string $userId, string $hash): bool
    {
        return DB::transaction(function () use ($userId, $hash): bool {
            $user = $this->query($userId)->lockForUpdate()->first();
            $hashes = $user instanceof User ? ($user->two_factor_recovery_hashes ?? []) : [];
            $index = array_search($hash, $hashes, true);
            if ($index === false) {
                return false;
            }
            unset($hashes[$index]);
            $this->query($userId)->update(['two_factor_recovery_hashes' => json_encode(array_values($hashes), JSON_THROW_ON_ERROR)]);

            return true;
        });
    }

    public function passwordHash(string $userId): string
    {
        return (string) $this->user($userId)->getAuthPassword();
    }

    public function accountLabel(string $userId): string
    {
        return $this->user($userId)->email;
    }

    /**
     * @return Builder<User>
     */
    private function query(string $userId)
    {
        return User::query()->withoutGlobalScope(ClinicScope::class)->whereKey($userId);
    }

    private function user(string $userId): User
    {
        return $this->query($userId)->firstOrFail();
    }
}
