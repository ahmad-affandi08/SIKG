<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Patient\Domain\Import\ImportStatus;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\PatientNotFound;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * PAS-08, tahap konfirmasi: batch yang sudah dipratinjau mulai diimpor per potongan lewat antrean.
 */
final readonly class CommitPatientImport
{
    public function __construct(
        private ImportBatchRepository $batches,
        private ImportChunkScheduler $scheduler,
        private Transaction $tx,
        private AuditLogger $audit,
    ) {}

    public function __invoke(string $actorId, string $batchId, bool $includeDuplicates): void
    {
        $this->tx->run(function () use ($actorId, $batchId, $includeDuplicates): void {
            $batch = $this->batches->findForUpdate($batchId) ?? throw new PatientNotFound;
            if ($batch->status !== ImportStatus::Previewed) {
                throw new InvalidPatientData('patient.import_not_previewed', 'Impor ini sudah dikonfirmasi atau sudah selesai.');
            }

            $this->batches->markImporting($batchId, $includeDuplicates);
            $this->audit->record(new AuditEntry($actorId, 'patient.import_committed', 'import_batch', $batchId, null, [
                'valid_rows' => $batch->validRows,
                'duplicate_rows' => $batch->duplicateRows,
                'include_duplicates' => $includeDuplicates,
            ]));
        });

        $this->scheduler->scheduleNext($batchId);
    }
}
