<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\HealthMonitor;

/**
 * SYS-06 dan SYS-07: halaman Admin baca-saja untuk kesiapan lingkungan dan denyut cron/antrean.
 */
final class SystemController
{
    public function __invoke(EnvironmentInspector $inspector, HealthMonitor $health): Response
    {
        return Inertia::render('Settings/System', [
            'checks' => array_map(fn ($c) => ['key' => $c->key, 'label' => $c->label, 'status' => $c->status, 'detail' => $c->detail], $inspector->inspect()),
            'health' => $health->snapshot()->toArray(),
            'hostingProfile' => (string) config('sikg.hosting_profile'),
        ]);
    }
}
