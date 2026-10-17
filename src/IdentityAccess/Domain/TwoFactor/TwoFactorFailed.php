<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain\TwoFactor;

use Src\Shared\Domain\DomainException;

final class TwoFactorFailed extends DomainException
{
    public static function invalidCode(): self
    {
        return new self('auth.two_factor_invalid', 'Kode verifikasi tidak valid. Periksa kode di aplikasi autentikator atau pakai kode pemulihan.');
    }

    public static function notPending(): self
    {
        return new self('auth.two_factor_not_pending', 'Penyiapan verifikasi dua langkah belum dimulai atau sudah kedaluwarsa. Mulai lagi.');
    }

    public static function alreadyEnabled(): self
    {
        return new self('auth.two_factor_already_enabled', 'Verifikasi dua langkah sudah aktif.');
    }

    public static function notEnabled(): self
    {
        return new self('auth.two_factor_not_enabled', 'Verifikasi dua langkah belum aktif.');
    }
}
