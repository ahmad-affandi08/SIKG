<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure\Import;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Patient\Application\Import\ProcessImportChunk;
use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Shared\Application\TenantContext;
use Throwable;

/**
 * Satu potongan impor pasien. Pendek dan idempoten (penunjuk `nextRow` di batch); potongan berikutnya dijadwalkan oleh
 * ProcessImportChunk. Hanya id batch yang dibawa job, tanpa data pasien.
 */
final class ProcessPatientImportChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $batchId, public readonly string $clinicId, public readonly ?string $branchId) {}

    public function handle(ProcessImportChunk $process, TenantContext $tenant): void
    {
        $tenant->runAs($this->clinicId, $this->branchId, fn () => $process($this->batchId));
    }

    public function failed(Throwable $e): void
    {
        app(TenantContext::class)->runAs($this->clinicId, $this->branchId, function (): void {
            app(ImportBatchRepository::class)->markFailed($this->batchId, 'Impor berhenti karena galat sistem. Hubungi pengelola teknis.');
        });
    }
}
