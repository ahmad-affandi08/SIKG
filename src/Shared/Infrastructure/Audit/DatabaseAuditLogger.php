<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Audit;

use Illuminate\Http\Request;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TenantContext;

final readonly class DatabaseAuditLogger implements AuditLogger
{
    public function __construct(
        private TenantContext $tenant,
        private Clock $clock,
        private Request $request,
    ) {}

    public function record(AuditEntry $entry): void
    {
        $log = new AuditLogModel;
        $log->forceFill([
            'clinic_id' => $this->tenant->clinicId(),
            'branch_id' => $this->tenant->branchId(),
            'actor_id' => $entry->actorId,
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_id' => $entry->subjectId,
            'before' => $entry->before,
            'after' => $entry->after,
            'ip' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
            'created_at' => $this->clock->now(),
        ])->save();
    }
}
