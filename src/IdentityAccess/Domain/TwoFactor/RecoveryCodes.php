<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain\TwoFactor;

/**
 * Kode pemulihan sekali pakai untuk saat perangkat autentikator hilang. Hanya hash yang disimpan; kode asli tampil sekali.
 */
final class RecoveryCodes
{
    public const COUNT = 10;

    /** Tanpa huruf yang mudah tertukar (0/o, 1/l/i). */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /**
     * @return list<string> contoh "k3m9p-x2tqa"
     */
    public static function generate(int $count = self::COUNT): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $raw = '';
            for ($i = 0; $i < 10; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        return array_values(array_unique($codes));
    }

    /** Bentuk baku untuk dicocokkan: huruf kecil tanpa spasi; null bila bukan bentuk kode pemulihan. */
    public static function normalize(string $input): ?string
    {
        $text = strtolower(str_replace([' ', '-'], '', trim($input)));

        return preg_match('/^['.self::ALPHABET.']{10}$/', $text) === 1 ? substr($text, 0, 5).'-'.substr($text, 5) : null;
    }

    public static function hash(string $normalized, string $key): string
    {
        return hash_hmac('sha256', $normalized, $key);
    }
}
