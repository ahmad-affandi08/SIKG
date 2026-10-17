<?php

declare(strict_types=1);

namespace Src\Shared\Application;

interface HealthMonitor
{
    /**
     * Mencatat denyut komponen (`scheduler` atau `queue`).
     *
     * @param  'scheduler'|'queue'  $component
     */
    public function beat(string $component): void;

    public function snapshot(): HealthSnapshot;
}
