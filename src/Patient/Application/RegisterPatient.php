<?php

declare(strict_types=1);

namespace Src\Patient\Application;

use Src\Clinic\Contracts\MedicalRecordNumbering;
use Src\Patient\Domain\ConsentText;
use Src\Patient\Domain\DuplicatePatientSuspected;
use Src\Patient\Domain\Events\PatientRegistered;
use Src\Patient\Domain\InvalidPatientData;
use Src\Patient\Domain\PatientRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Transaction;

/**
 * PAS-01, PAS-03, PAS-05, PAS-06: mendaftarkan pasien baru. Nomor RM dibuat otomatis dari urutan klinik. Bila ada
 * pasien mirip, pendaftaran ditahan sampai petugas mengonfirmasi bahwa itu orang yang berbeda.
 */
final readonly class RegisterPatient
{
    public function __construct(
        private PatientRepository $patients,
        private MedicalRecordNumbering $numbering,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  bool  $acknowledgeDuplicates  petugas sudah melihat calon duplikat dan tetap mendaftarkan
     * @param  bool  $consentGiven  pasien menyetujui teks persetujuan versi sekarang
     * @return string id pasien
     */
    public function __invoke(string $actorId, PatientDraft $draft, bool $consentGiven, bool $acknowledgeDuplicates = false): string
    {
        $now = $this->clock->now();
        $profile = $draft->toProfile($now);
        if (! $consentGiven) {
            throw new InvalidPatientData('patient.consent_required', 'Persetujuan pemrosesan data pribadi pasien wajib dicatat.');
        }

        $id = $this->tx->run(function () use ($actorId, $profile, $acknowledgeDuplicates, $now): string {
            if (! $acknowledgeDuplicates && $this->patients->findDuplicates($profile) !== []) {
                throw new DuplicatePatientSuspected;
            }

            $number = $this->numbering->next();
            $id = $this->patients->add($actorId, $number, $profile);
            $this->patients->recordConsent($actorId, $id, ConsentText::VERSION, $now);

            $this->audit->record(new AuditEntry($actorId, 'patient.registered', 'patient', $id, null, ['medical_record_no' => $number] + $profile->auditView()));

            return $id;
        });

        $this->events->dispatch(new PatientRegistered($id, $now));

        return $id;
    }
}
