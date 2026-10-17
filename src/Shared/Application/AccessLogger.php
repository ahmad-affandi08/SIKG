<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Mencatat pembukaan rekam medis pasien (siapa, kapan, pasien mana) ke access_logs (M10). Hanya INSERT.
 */
interface AccessLogger
{
    public function patientRecordOpened(string $patientId, string $route): void;
}
