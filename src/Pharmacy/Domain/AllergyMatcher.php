<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

/**
 * OBT-02: kelas obat dibandingkan dengan nama alergi pasien. Cocok bila salah satunya memuat yang lain setelah dinormalkan
 * (huruf kecil, garis bawah menjadi spasi): kelas "penisilin" cocok dengan alergi "Alergi penisilin" atau "Penisilin".
 */
final class AllergyMatcher
{
    public static function matches(?string $drugClass, string $allergyName): bool
    {
        if ($drugClass === null || trim($drugClass) === '') {
            return false;
        }
        $class = self::normalize($drugClass);
        $allergy = self::normalize($allergyName);

        return $class !== '' && $allergy !== '' && (str_contains($allergy, $class) || str_contains($class, $allergy));
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('_', ' ', mb_strtolower($text))));
    }
}
