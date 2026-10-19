<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

/**
 * Nomor WhatsApp Indonesia dalam bentuk internasional tanpa plus (628123456789). Dipakai untuk tujuan pemberitahuan staf dan
 * pengiriman uji. Tidak pernah ditulis ke log.
 */
final readonly class WhatsAppNumber
{
    /** Bentuk normal, contoh 628123456789. */
    public string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function tryFrom(string $input): ?self
    {
        $digits = ltrim((string) preg_replace('/[\s\-().]/', '', $input), '+');
        if (preg_match('/^\d{8,15}$/', $digits) !== 1) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return str_starts_with($digits, '62') && strlen($digits) >= 10 && strlen($digits) <= 15 ? new self($digits) : null;
    }

    /** Contoh: 0812****6789 */
    public function masked(): string
    {
        $local = '0'.substr($this->value, 2);

        return substr($local, 0, 4).str_repeat('*', max(strlen($local) - 8, 0)).substr($local, -4);
    }
}
