<?php

declare(strict_types=1);

namespace Src\Scheduling\Contracts;

/**
 * Kontrak baca janji bagi konteks lain (Clinical membuat kunjungan dari janji).
 */
interface AppointmentLookup
{
    public function find(string $appointmentId): ?AppointmentInfo;
}
