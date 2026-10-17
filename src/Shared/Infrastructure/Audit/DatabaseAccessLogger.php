<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;

final readonly class DatabaseAccessLogger implements AccessLogger
{
    public function __construct(
        private TenantContext $tenant,
        private Clock $clock,
        private Request $request,
        private Guard $auth,
    ) {}

    public function patientRecordOpened(string $patientId, string $route): void
    {
        $actorId = $this->auth->id();

        $log = new AccessLogModel;
        $log->forceFill([
            'clinic_id' => $this->tenant->clinicId(),
            'branch_id' => $this->tenant->branchId(),
            'actor_id' => is_string($actorId) ? $actorId : null,
            'patient_id' => $patientId,
            'route' => mb_substr($route, 0, 150),
            'ip' => $this->request->ip(),
            'at' => $this->clock->now(),
        ])->save();
    }
}
