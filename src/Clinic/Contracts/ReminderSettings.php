<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Pengaturan klinik untuk pengingat janji pasien (JDW-07, SYS-04): apakah klinik mengaktifkannya.
 */
interface ReminderSettings
{
    public function enabled(): bool;
}
