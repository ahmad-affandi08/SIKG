<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Nomor HP atau WhatsApp Indonesia dinormalkan ke bentuk internasional tanpa plus (628123456789) agar pencarian dan
 * deteksi duplikat tidak terkecoh format. Tidak pernah ditulis ke log.
 */
final readonly class PhoneNumber
{
    /** Bentuk normal, contoh 628123456789. */
    public string $value;

    public function __construct(string $input)
    {
        $digits = preg_replace('/[\s\-().]/', '', $input) ?? '';
        $digits = ltrim($digits, '+');
        if (preg_match('/^\d{8,15}$/', $digits) !== 1) {
            throw new InvalidPatientData('patient.invalid_phone', 'Nomor HP tidak valid.');
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }
        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidPatientData('patient.invalid_phone', 'Nomor HP harus nomor Indonesia (08..., 628..., atau +628...).');
        }
        $this->value = $digits;
    }

    /** Untuk mengenali masukan pencarian yang berupa nomor HP. */
    public static function tryFrom(string $input): ?self
    {
        try {
            return new self($input);
        } catch (InvalidPatientData) {
            return null;
        }
    }

    /** Bentuk tampil lokal, contoh 08123456789. */
    public function local(): string
    {
        return '0'.substr($this->value, 2);
    }

    /** Contoh: 0812****6789 */
    public function masked(): string
    {
        $local = $this->local();

        return substr($local, 0, 4).str_repeat('*', max(strlen($local) - 8, 0)).substr($local, -4);
    }
}
