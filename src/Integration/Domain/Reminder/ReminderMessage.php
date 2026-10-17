<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

/**
 * Merakit pesan pengingat dari templat dengan penanda {nama}, {tanggal}, {jam}, {dokter}, {klinik}, dan {bulan}. Murni:
 * tanpa data pasien yang disimpan; hasilnya hanya hidup selama ditampilkan atau dikirim.
 */
final class ReminderMessage
{
    public const PLACEHOLDERS = ['nama', 'tanggal', 'jam', 'dokter', 'klinik', 'bulan'];

    /**
     * @param  array<string, string>  $values
     */
    public static function render(string $template, array $values): string
    {
        $replace = [];
        foreach (self::PLACEHOLDERS as $key) {
            $replace['{'.$key.'}'] = $values[$key] ?? '';
        }

        return trim((string) preg_replace('/\s{2,}/', ' ', strtr($template, $replace)));
    }

    /**
     * Penanda di templat yang tidak dikenal.
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{([^{}]*)\}/', $template, $matches);

        return array_values(array_unique(array_filter($matches[1], fn (string $name) => ! in_array($name, self::PLACEHOLDERS, true))));
    }

    /** Tautan klik-untuk-chat WhatsApp (gratis): nomor internasional tanpa plus. */
    public static function whatsappLink(string $phone, string $text): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', $phone).'?text='.rawurlencode($text);
    }
}
