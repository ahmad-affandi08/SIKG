<?php

declare(strict_types=1);

namespace Src\Patient\Application\Import;

use Src\Patient\Domain\Import\ImportBatchRepository;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Throwable;

/**
 * PAS-08, tahap pratinjau: berkas disimpan, tiap baris diperiksa per potongan, dan hasilnya (baris valid, galat, dugaan
 * duplikat) dicatat di batch. Belum ada pasien yang dibuat sebelum petugas mengonfirmasi.
 */
final readonly class StartPatientImport
{
    private const MAX_STORED_ERRORS = 500;

    public function __construct(
        private ImportFileStore $files,
        private SpreadsheetReader $reader,
        private ImportRowMapper $mapper,
        private PatientRepository $patients,
        private ImportBatchRepository $batches,
        private Transaction $tx,
        private AuditLogger $audit,
        private Clock $clock,
        private int $maxRows,
        private int $chunkSize,
    ) {}

    /**
     * @return string id batch
     *
     * @throws ImportFileInvalid
     */
    public function __invoke(string $actorId, string $contents): string
    {
        $path = $this->files->put($contents);

        try {
            $this->files->withLocalCopy($path, $this->assertReadable(...));
            $batchId = $this->tx->run(fn () => $this->batches->create($actorId, $path));
            $this->analyze($batchId, $path);
        } catch (Throwable $e) {
            $this->files->delete($path);
            throw $e;
        }

        $this->audit->record(new AuditEntry($actorId, 'patient.import_previewed', 'import_batch', $batchId));

        return $batchId;
    }

    private function assertReadable(string $local): void
    {
        $total = $this->reader->countRows($local);
        if ($total === 0) {
            throw new ImportFileInvalid('Berkas tidak memuat baris data.');
        }
        if ($total > $this->maxRows) {
            throw new ImportFileInvalid("Berkas berisi {$total} baris; maksimal {$this->maxRows} baris per impor. Pecah berkas lalu impor bertahap.");
        }
    }

    private function analyze(string $batchId, string $path): void
    {
        $this->files->withLocalCopy($path, function (string $local) use ($batchId): void {
            $total = $this->reader->countRows($local);
            $today = $this->clock->now();
            $seenNik = [];
            $valid = 0;
            $errorRows = 0;
            $errors = [];
            $duplicates = [];

            for ($offset = 0; $offset < $total; $offset += $this->chunkSize) {
                foreach ($this->reader->read($local, $offset, $this->chunkSize) as $rowNumber => $row) {
                    if ($this->mapper->isEmpty($row)) {
                        continue;
                    }
                    try {
                        $profile = $this->mapper->map($row, $today)['profile'];
                    } catch (InvalidPatientData $e) {
                        $errorRows++;
                        if (count($errors) < self::MAX_STORED_ERRORS) {
                            $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
                        }

                        continue;
                    }

                    $nik = $profile->nik?->value;
                    $duplicateInFile = $nik !== null && isset($seenNik[$nik]);
                    if ($nik !== null) {
                        $seenNik[$nik] = true;
                    }

                    if ($duplicateInFile || $this->patients->findDuplicates($profile) !== []) {
                        $duplicates[] = $rowNumber;
                    } else {
                        $valid++;
                    }
                }
            }

            $this->batches->markPreviewed($batchId, $total, $valid, $errorRows, $errors, $duplicates);
        });
    }
}
