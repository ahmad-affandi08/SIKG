<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * SYS-06: memeriksa kesiapan lingkungan (versi PHP, ekstensi, izin tulis, batas unggah, driver, keamanan dasar).
 */
interface EnvironmentInspector
{
    /**
     * @return list<PreflightCheck>
     */
    public function inspect(): array;
}
