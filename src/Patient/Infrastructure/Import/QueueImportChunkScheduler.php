<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Import;

use LogicException;
use Src\Patient\Application\Import\ImportChunkScheduler;
use Src\Shared\Application\TenantContext;

final readonly class QueueImportChunkScheduler implements ImportChunkScheduler
{
    public function __construct(private TenantContext $tenant) {}

    public function scheduleNext(string $batchId): void
    {
        $clinicId = $this->tenant->clinicId() ?? throw new LogicException('Konteks klinik kosong; impor tidak dapat dijadwalkan.');

        ProcessPatientImportChunkJob::dispatch($batchId, $clinicId, $this->tenant->branchId());
    }
}
