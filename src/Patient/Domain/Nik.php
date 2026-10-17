<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

/**
 * Nomor Induk Kependudukan: 16 digit. Nilai disimpan terenkripsi; kelas ini hanya memeriksa bentuk dan membuat
 * tampilan tersamar. Tidak pernah ditulis ke log.
 */
final readonly class Nik
{
    public string $value;

    public function __construct(string $value)
    {
        $digits = preg_replace('/\s+/', '', $value) ?? '';
        if (preg_match('/^\d{16}$/', $digits) !== 1 || preg_match('/^(\d)\1{15}$/', $digits) === 1) {
            throw new InvalidPatientData('patient.invalid_nik', 'NIK harus 16 digit angka.');
        }
        $this->value = $digits;
    }

    public static function looksLikeNik(string $input): bool
    {
        return preg_match('/^\d{16}$/', preg_replace('/\s+/', '', $input) ?? '') === 1;
    }

    /** Contoh: ************3456 */
    public function masked(): string
    {
        return str_repeat('*', 12).substr($this->value, -4);
    }
}
