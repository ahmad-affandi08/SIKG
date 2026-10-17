<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application\TwoFactor;

use DateTimeImmutable;

/**
 * AUTH-05: penyimpanan faktor kedua per pengguna. Port Application untuk konteks CRUD (ADR-0001); implementasi Eloquent di
 * Infrastructure. Rahasia TOTP dienkripsi di penyimpanan (M11); kode pemulihan hanya berupa hash.
 */
interface TwoFactorStore
{
    public function isEnabled(string $userId): bool;

    /** Rahasia yang menunggu konfirmasi, atau null. */
    public function pendingSecret(string $userId): ?string;

    public function putPending(string $userId, string $secret): void;

    /** Rahasia aktif, atau null bila verifikasi dua langkah belum aktif. */
    public function activeSecret(string $userId): ?string;

    /**
     * @param  list<string>  $recoveryHashes
     */
    public function activate(string $userId, string $secret, array $recoveryHashes, int $step, DateTimeImmutable $at): void;

    public function disable(string $userId): void;

    /**
     * @param  list<string>  $recoveryHashes
     */
    public function replaceRecovery(string $userId, array $recoveryHashes): void;

    public function recoveryRemaining(string $userId): int;

    /** Langkah TOTP terakhir yang dipakai; kode pada langkah itu atau sebelumnya ditolak. */
    public function lastStep(string $userId): int;

    /** Mencatat langkah terpakai. false bila langkah itu (atau yang lebih baru) sudah dipakai, yaitu kode diulang. */
    public function advanceStep(string $userId, int $step): bool;

    /** Memakai satu kode pemulihan. false bila tidak ada atau sudah terpakai. */
    public function consumeRecovery(string $userId, string $hash): bool;

    public function passwordHash(string $userId): string;

    /** Label akun untuk aplikasi autentikator (email pengguna). */
    public function accountLabel(string $userId): string;
}
