<?php

declare(strict_types=1);

namespace Src\Clinical\Application;

use Src\Scheduling\Domain\Events\PatientCheckedIn;

/**
 * Menghubungkan event PatientCheckedIn (Scheduling) ke pembuatan kunjungan (RM-01).
 */
final readonly class OpenEncounterListener
{
    public function __construct(private OpenEncounter $open) {}

    public function handle(PatientCheckedIn $event): void
    {
        $this->open->onCheckIn($event);
    }
}
