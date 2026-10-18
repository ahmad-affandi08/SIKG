<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master\Import;

use Src\Shared\Application\Master\MasterField;

/**
 * Menyeragamkan isian sel sebelum divalidasi: uang dan angka tanpa "Rp" dan titik ribuan, pilihan dicocokkan lewat nilai atau
 * label, tanggal DD/MM/YYYY menjadi YYYY-MM-DD, dan sel kosong menjadi null. Nilai yang tidak dikenali dibiarkan apa adanya
 * agar ditolak aturan validasi dengan pesan yang jelas.
 */
final class MasterValueNormalizer
{
    /**
     * @param  list<MasterField>  $fields
     * @param  array<string, string>  $raw
     * @return array<string, string|null>
     */
    public static function normalize(array $fields, array $raw): array
    {
        $out = [];
        foreach ($fields as $field) {
            $value = trim($raw[$field->key] ?? '');
            $out[$field->key] = $value === '' ? null : self::one($field, $value);
        }

        return $out;
    }

    private static function one(MasterField $field, string $value): string
    {
        return match ($field->type) {
            'money', 'number' => self::digits($value),
            'select' => self::option($field, $value),
            'date' => self::date($value),
            default => $value,
        };
    }

    private static function digits(string $value): string
    {
        $text = trim((string) preg_replace('/^\s*rp\.?\s*/i', '', $value));
        $withoutDots = str_replace('.', '', $text);

        return preg_match('/^\d+$/', $withoutDots) === 1 ? $withoutDots : $value;
    }

    private static function option(MasterField $field, string $value): string
    {
        $needle = mb_strtolower($value);
        foreach ($field->options as $option) {
            if (mb_strtolower($option['value']) === $needle || mb_strtolower($option['label']) === $needle) {
                return $option['value'];
            }
        }

        return $value;
    }

    private static function date(string $value): string
    {
        if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $value;
    }
}
