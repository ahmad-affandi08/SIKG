<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

/**
 * Penyimpanan berkas klinis di disk privat, dienkripsi di aplikasi bila diatur (docs keamanan: profil hosting bersama).
 */
interface FileVault
{
    /**
     * @param  string  $extension  jpg, png, atau pdf
     * @return array{path: string, encrypted: bool}
     */
    public function put(string $patientId, string $contents, string $extension): array;

    public function get(string $path, bool $encrypted): string;
}
