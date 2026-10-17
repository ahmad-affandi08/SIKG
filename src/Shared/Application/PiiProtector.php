<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Perlindungan data pribadi di kolom (M11): enkripsi untuk disimpan dan blind index (HMAC) untuk pencarian persis.
 * Nilai yang dilindungi (NIK, nomor HP) tidak pernah ditulis ke log atau URL.
 */
interface PiiProtector
{
    public function encrypt(string $plain): string;

    public function decrypt(string $cipher): string;

    /**
     * Hash deterministik untuk pencarian persis. `$purpose` memisahkan ruang hash (contoh `nik`, `phone`)
     * sehingga nilai yang sama pada dua kolom tidak menghasilkan hash yang sama.
     *
     * @param  non-empty-string  $purpose
     */
    public function blindIndex(string $purpose, string $normalized): string;
}
