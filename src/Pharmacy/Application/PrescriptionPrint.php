<?php

declare(strict_types=1);

namespace Src\Pharmacy\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\LetterheadProvider;
use Src\Patient\Contracts\PatientLookup;
use Src\Pharmacy\Domain\InvalidPharmacyData;
use Src\Pharmacy\Domain\PrescriptionRepository;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;

/**
 * OBT-03: data cetak resep dengan kop klinik dan nama dokter. Membuka resep untuk dicetak tercatat di access log dan audit.
 */
final readonly class PrescriptionPrint
{
    public function __construct(
        private PrescriptionRepository $prescriptions,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private LetterheadProvider $letterhead,
        private ClinicCalendar $calendar,
        private AccessLogger $access,
        private AuditLogger $audit,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, mixed> data untuk templat cetak
     */
    public function data(string $actorId, string $prescriptionId): array
    {
        $prescription = $this->prescriptions->find($prescriptionId) ?? throw new InvalidPharmacyData('prescription.not_found', 'Resep tidak ditemukan.');
        $patients = $this->patients->summaries([$prescription->patientId]);
        $patient = isset($patients[$prescription->patientId]) ? $patients[$prescription->patientId] : null;
        $doctorMap = $this->doctors->byIds([$prescription->doctorId]);
        $doctor = isset($doctorMap[$prescription->doctorId]) ? $doctorMap[$prescription->doctorId] : null;
        $head = $this->letterhead->forPrescription();
        $this->access->patientRecordOpened($prescription->patientId, 'prescriptions.print');
        $this->audit->record(new AuditEntry($actorId, 'prescription.printed', 'prescription', $prescriptionId));

        $age = null;
        if ($patient?->birthDate !== null) {
            $age = (new DateTimeImmutable($patient->birthDate))->diff($this->clock->now())->y;
        }

        return [
            'letterhead' => ['name' => $head->clinicName, 'address' => $head->address, 'license' => $head->licenseNo, 'header' => $head->headerText, 'logo' => $head->logoDataUri],
            'void' => $prescription->isVoid(),
            'issuedAt' => $prescription->issuedAt->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('d/m/Y H:i'),
            'patient' => ['name' => $patient->name ?? '-', 'mrn' => $patient->medicalRecordNo ?? '-', 'age' => $age],
            'doctor' => ['name' => $doctor->name ?? '-', 'sip' => $doctor?->sipNo],
            'items' => array_map(fn ($i) => $i->toArray(), $prescription->items),
        ];
    }
}
