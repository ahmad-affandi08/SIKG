<?php

declare(strict_types=1);

namespace Src\Clinical\Presentation\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Src\Clinical\Application\PatientTimeline;

/**
 * RM-11: linimasa riwayat pasien dalam satu halaman.
 */
final class TimelineController
{
    public function __invoke(string $patient, PatientTimeline $timeline): Response
    {
        return Inertia::render('Patients/Timeline', $timeline($patient, 'patients.timeline'));
    }
}
