<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Domain\TwoFactor;

/**
 * Base32 RFC 4648 tanpa padding, alfabet standar yang dipakai aplikasi autentikator.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    /**
     * @return string|null null bila ada karakter di luar alfabet
     */
    public static function decode(string $text): ?string
    {
        $text = strtoupper(str_replace([' ', '-', '='], '', $text));
        $bits = '';
        foreach (str_split($text) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                return null;
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
