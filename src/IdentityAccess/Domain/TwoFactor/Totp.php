<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain\TwoFactor;

/**
 * Kata sandi sekali pakai berbasis waktu (TOTP, RFC 6238) dengan HMAC-SHA1, 6 digit, langkah 30 detik: bentuk yang dipahami
 * semua aplikasi autentikator. Tanpa pustaka tambahan (M1). Toleransi satu langkah ke depan dan ke belakang untuk selisih jam.
 */
final class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    private const WINDOW = 1;

    private const SECRET_BYTES = 20;

    public static function newSecret(): string
    {
        return Base32::encode(random_bytes(self::SECRET_BYTES));
    }

    public static function step(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD);
    }

    public static function code(string $secret, int $step): string
    {
        $key = Base32::decode($secret) ?? '';
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Langkah waktu yang cocok dengan kode, atau null. Langkah yang sudah dipakai (kurang dari atau sama dengan `$afterStep`)
     * ditolak sehingga satu kode tidak dapat dipakai dua kali.
     */
    public static function verify(string $secret, string $code, int $timestamp, int $afterStep = 0): ?int
    {
        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        $current = self::step($timestamp);
        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
            if ($step > $afterStep && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** Alamat otpauth yang dapat dimasukkan ke aplikasi autentikator. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
