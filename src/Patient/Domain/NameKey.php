<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Kunci pencarian nama: huruf kecil, tanpa tanda baca, spasi tunggal. Dipakai untuk kolom `name_search` dan
 * perbandingan kemiripan nama pada deteksi duplikat.
 */
final class NameKey
{
    public static function of(string $name): string
    {
        $lower = mb_strtolower($name);
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower) ?? '';

        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    /**
     * Nama dianggap mirip bila sama atau selisih penulisan kecil (maksimal dua karakter) setelah dinormalkan.
     */
    public static function similar(string $a, string $b): bool
    {
        $a = self::of($a);
        $b = self::of($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        return levenshtein(substr($a, 0, 255), substr($b, 0, 255)) <= 2;
    }
}
