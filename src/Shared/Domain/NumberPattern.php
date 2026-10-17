<?php

declare(strict_types=1);

namespace Src\Shared\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Format nomor tampil yang dapat diatur klinik (nomor RM, nomor invoice). Token: {seq} atau {seq:N} (isi nol
 * sampai N digit), {yyyy}, {yy}, {mm}, {dd}. Nomor urut bertambah dari tabel `sequences` (ADR-0004);
 * kunci lingkupnya mengikuti token tanggal sehingga urutan mulai dari 1 lagi saat tahun atau bulan berganti.
 */
final readonly class NumberPattern
{
    private const TOKEN = '/\{(seq(?::(\d{1,2}))?|yyyy|yy|mm|dd)\}/';

    private const LITERAL = '/^[A-Za-z0-9\/_.\-]*$/';

    public string $pattern;

    public function __construct(string $pattern)
    {
        $literals = preg_replace(self::TOKEN, '', $pattern) ?? '';
        if (preg_match(self::LITERAL, $literals) !== 1) {
            throw new InvalidArgumentException('Format nomor hanya boleh huruf, angka, dan / _ . - di luar token.');
        }
        if (preg_match_all('/\{seq(?::\d{1,2})?\}/', $pattern) !== 1) {
            throw new InvalidArgumentException('Format nomor harus memuat tepat satu token {seq}.');
        }
        if (mb_strlen($pattern) > 40) {
            throw new InvalidArgumentException('Format nomor terlalu panjang (maksimal 40 karakter).');
        }

        $this->pattern = $pattern;
    }

    public function render(int $sequence, DateTimeImmutable $at, string $timezone = 'UTC'): string
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException('Nomor urut dimulai dari 1.');
        }
        $local = $at->setTimezone(new DateTimeZone($timezone));

        return (string) preg_replace_callback(self::TOKEN, static function (array $m) use ($sequence, $local): string {
            return match (true) {
                str_starts_with($m[1], 'seq') => str_pad((string) $sequence, (int) ($m[2] ?? 1), '0', STR_PAD_LEFT),
                $m[1] === 'yyyy' => $local->format('Y'),
                $m[1] === 'yy' => $local->format('y'),
                $m[1] === 'mm' => $local->format('m'),
                default => $local->format('d'),
            };
        }, $this->pattern);
    }

    /**
     * Lingkup nomor urut: kosong bila tanpa token tanggal, tahun bila ada {yyyy}/{yy}, tahun-bulan bila ada {mm}, dst.
     */
    public function scopeKey(DateTimeImmutable $at, string $timezone = 'UTC'): string
    {
        $local = $at->setTimezone(new DateTimeZone($timezone));
        $parts = [];
        if (str_contains($this->pattern, '{yyyy}') || str_contains($this->pattern, '{yy}')) {
            $parts[] = $local->format('Y');
        }
        if (str_contains($this->pattern, '{mm}')) {
            $parts[] = $local->format('m');
        }
        if (str_contains($this->pattern, '{dd}')) {
            $parts[] = $local->format('d');
        }

        return implode('-', $parts);
    }
}
