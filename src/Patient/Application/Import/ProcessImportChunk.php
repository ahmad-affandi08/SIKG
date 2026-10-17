<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

use Src\Clinic\Contracts\MedicalRecordNumbering;
use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Patient\Domain\Import\ImportStatus;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * PAS-08, satu potongan impor. Aman dijalankan dua kali: potongan dikunci pada baris batch dan penunjuk `nextRow`
 * maju dalam transaksi yang sama dengan penyimpanan pasien. Baris galat dilewati; dugaan duplikat dilewati kecuali
 * petugas memilih ikut mengimpornya. Pasien hasil impor belum memiliki persetujuan data (dicatat menyusul, PAS-05).
 */
final readonly class ProcessImportChunk
{
    public function __construct(
        private ImportFileStore $files,
        private SpreadsheetReader $reader,
        private ImportRowMapper $mapper,
        private PatientRepository $patients,
        private ImportBatchRepository $batches,
        private MedicalRecordNumbering $numbering,
        private ImportChunkScheduler $scheduler,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
        private int $chunkSize,
    ) {}

    public function __invoke(string $batchId): void
    {
        $more = $this->tx->run(function () use ($batchId): bool {
            $batch = $this->batches->findForUpdate($batchId);
            if ($batch === null || $batch->status !== ImportStatus::Importing) {
                return false;
            }

            $imported = $batch->importedRows;
            $skipped = $batch->skippedRows;
            $rows = $this->files->withLocalCopy($batch->filePath, fn (string $local) => $this->reader->read($local, $batch->nextRow, $this->chunkSize));
            $today = $this->clock->now();

            foreach ($rows as $row) {
                if ($this->mapper->isEmpty($row)) {
                    continue;
                }
                try {
                    $mapped = $this->mapper->map($row, $today);
                } catch (InvalidPatientData) {
                    $skipped++;

                    continue;
                }

                if (! $batch->includeDuplicates && $this->patients->findDuplicates($mapped['profile']) !== []) {
                    $skipped++;

                    continue;
                }

                $number = $this->numbering->next();
                $id = $this->patients->add($batch->createdBy, $number, $mapped['profile']);
                foreach ($mapped['histories'] as $history) {
                    $this->patients->addHistory($batch->createdBy, $id, $history);
                }
                $this->audit->record(new AuditEntry($batch->createdBy, 'patient.imported', 'patient', $id, null, ['medical_record_no' => $number, 'batch_id' => $batchId]));
                $imported++;
            }

            $next = $batch->nextRow + $this->chunkSize;
            $this->batches->advance($batchId, min($next, $batch->totalRows), $imported, $skipped);

            if ($next >= $batch->totalRows) {
                $this->batches->markCompleted($batchId);
                $this->audit->record(new AuditEntry($batch->createdBy, 'patient.import_completed', 'import_batch', $batchId, null, ['imported_rows' => $imported, 'skipped_rows' => $skipped]));

                return false;
            }

            return true;
        });

        if ($more) {
            $this->scheduler->scheduleNext($batchId);
        } else {
            $batch = $this->batches->find($batchId);
            if ($batch !== null && $batch->status === ImportStatus::Completed) {
                $this->files->delete($batch->filePath);
            }
        }
    }
}
