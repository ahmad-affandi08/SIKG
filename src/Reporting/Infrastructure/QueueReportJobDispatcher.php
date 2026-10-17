<?php

declare(strict_types=1);

namespace Src\Reporting\Infrastructure;

use RuntimeException;
use Src\Reporting\Application\ReportJobDispatcher;
use Src\Shared\Application\TenantContext;

final readonly class QueueReportJobDispatcher implements ReportJobDispatcher
{
    public function __construct(private TenantContext $tenant) {}

    public function dispatch(string $jobId): void
    {
        $clinic = $this->tenant->clinicId() ?? throw new RuntimeException('Konteks klinik kosong.');
        BuildReportFileJob::dispatch($jobId, $clinic, $this->tenant->branchId())->afterCommit();
    }
}
