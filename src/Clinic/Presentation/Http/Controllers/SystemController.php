<?php

declare(strict_types=1);

namespace Src\Clinic\Presentation\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Src\Shared\Application\Backup\BackupService;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\HealthMonitor;

/**
 * SYS-06, SYS-07, dan SYS-02: halaman Admin baca-saja untuk kesiapan lingkungan, denyut cron/antrean, dan keadaan cadangan.
 */
final class SystemController
{
    public function __invoke(EnvironmentInspector $inspector, HealthMonitor $health, BackupService $backups): Response
    {
        return Inertia::render('Settings/System', [
            'checks' => array_map(fn ($c) => ['key' => $c->key, 'label' => $c->label, 'status' => $c->status, 'detail' => $c->detail], $inspector->inspect()),
            'health' => $health->snapshot()->toArray(),
            'backup' => $backups->status(),
            'hostingProfile' => (string) config('sikg.hosting_profile'),
        ]);
    }
}
