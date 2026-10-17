<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Patient\Domain\ConsentText;
use Src\Patient\Domain\PatientNotFound;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * PAS-05: mencatat persetujuan pemrosesan data pribadi (tanggal, versi teks, petugas pencatat). Dipakai untuk pasien
 * hasil impor atau saat teks persetujuan berganti versi.
 */
final readonly class RecordConsent
{
    public function __construct(private PatientRepository $patients, private Transaction $tx, private AuditLogger $audit, private Clock $clock) {}

    public function __invoke(string $actorId, string $patientId): void
    {
        $this->tx->run(function () use ($actorId, $patientId): void {
            $this->patients->find($patientId) ?? throw new PatientNotFound;
            $this->patients->recordConsent($actorId, $patientId, ConsentText::VERSION, $this->clock->now());

            $this->audit->record(new AuditEntry($actorId, 'patient.consent_recorded', 'patient', $patientId, null, ['text_version' => ConsentText::VERSION]));
        });
    }
}
