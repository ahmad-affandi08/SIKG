<?php

declare(strict_types=1);

namespace Src\Integration\Application;

use DateInterval;
use DateTimeImmutable;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\ProcedureCatalog;
use Src\Clinical\Contracts\EncounterExportLookup;
use Src\Integration\Domain\Fhir\EncounterBundle;
use Src\Integration\Domain\IdentifierRepository;
use Src\Integration\Domain\SatuSehatFailure;
use Src\Integration\Domain\SatuSehatSettingsRepository;
use Src\Integration\Domain\SubmissionRecord;
use Src\Integration\Domain\SubmissionRepository;
use Src\Patient\Contracts\PatientIdentityLookup;
use Src\Shared\Application\Clock;

/**
 * P2-01: mengirim kunjungan yang sudah ditutup ke SATUSEHAT sebagai Bundle FHIR. Setiap kunjungan dikirim sekali (kunci unik per
 * kunjungan); galat sementara dicoba ulang dengan jeda bertahap sampai lima kali, galat permanen langsung ditandai gagal.
 * Data yang belum lengkap (NIK dokter, ID tidak ditemukan) menandai "perlu tindakan" dan tidak dicoba ulang otomatis.
 * Pesan galat yang disimpan tidak memuat data pasien maupun kredensial.
 */
final readonly class SubmitEncounters
{
    public const MAX_ATTEMPTS = 5;

    /** Jeda (menit) sebelum percobaan berikutnya setelah percobaan ke-1 sampai ke-4. */
    private const BACKOFF_MINUTES = [5, 15, 60, 240];

    public function __construct(
        private SubmissionRepository $submissions,
        private SatuSehatSettingsRepository $settings,
        private IdentifierRepository $identifiers,
        private SatuSehatGateway $gateway,
        private EncounterExportLookup $encounters,
        private PatientIdentityLookup $patients,
        private DoctorDirectory $doctors,
        private ProcedureCatalog $procedures,
        private Clock $clock,
    ) {}

    /**
     * @return array{sent: int, retry: int, failed: int, blocked: int, skipped: int}
     */
    public function run(int $limit = 20): array
    {
        $result = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'blocked' => 0, 'skipped' => 0];
        $settings = $this->settings->get();
        if (! $settings->enabled || ! $settings->isComplete()) {
            return $result;
        }

        foreach ($this->submissions->due($this->clock->now(), $limit) as $submission) {
            $outcome = $this->process($submission);
            $result[$outcome]++;
        }

        return $result;
    }

    /**
     * @return 'sent'|'retry'|'failed'|'blocked'|'skipped'
     */
    private function process(SubmissionRecord $submission): string
    {
        $settings = $this->settings->get();
        $export = $this->encounters->find($submission->encounterId);
        if ($export === null || ! $export->closed || $export->closedAt === null) {
            $this->submissions->markSkipped($submission->id, 'Kunjungan tidak ditemukan atau belum ditutup.');

            return 'skipped';
        }

        $identity = $this->patients->identity($submission->patientId);
        if ($identity === null || $identity->nik === null) {
            $this->submissions->markSkipped($submission->id, 'Pasien belum memiliki NIK sehingga tidak dapat dicocokkan di SATUSEHAT.');

            return 'skipped';
        }

        $doctor = $this->doctors->byIds([$export->doctorId])[$export->doctorId] ?? null;
        $doctorNik = $this->identifiers->practitionerNik($export->doctorId);
        if ($doctor === null || $doctorNik === null) {
            $this->submissions->markBlocked($submission->id, 'NIK dokter'.($doctor === null ? '' : ' '.$doctor->name).' belum diisi di pengaturan SATUSEHAT.');

            return 'blocked';
        }

        try {
            $patientIhs = $this->identifiers->patientIhs($identity->id) ?? $this->gateway->findPatientIhs($settings, $identity->nik);
            if ($patientIhs === null) {
                $this->submissions->markBlocked($submission->id, 'NIK pasien tidak ditemukan di SATUSEHAT. Periksa NIK pasien lalu kirim ulang.');

                return 'blocked';
            }
            $this->identifiers->rememberPatient($identity->id, $patientIhs);

            $practitionerIhs = $this->identifiers->practitionerIhs($export->doctorId) ?? $this->gateway->findPractitionerIhs($settings, $doctorNik);
            if ($practitionerIhs === null) {
                $this->submissions->markBlocked($submission->id, 'NIK dokter '.$doctor->name.' tidak ditemukan di SATUSEHAT. Periksa NIK di pengaturan SATUSEHAT lalu kirim ulang.');

                return 'blocked';
            }
            $this->identifiers->rememberPractitioner($export->doctorId, $practitionerIhs);

            $icd9 = $this->procedures->icd9Codes(array_map(fn (array $p) => $p['procedure_id'], $export->procedures));
            $bundle = EncounterBundle::build(
                (string) $settings->organizationId,
                (string) $settings->locationId,
                $settings->locationName,
                $export->id,
                $patientIhs,
                $identity->name,
                $practitionerIhs,
                $doctor->name,
                $export->startedAt,
                $export->closedAt,
                $export->diagnoses,
                array_values(array_map(
                    fn (array $p) => ['code' => $icd9[$p['procedure_id']], 'name' => $p['name']],
                    array_filter($export->procedures, fn (array $p) => isset($icd9[$p['procedure_id']])),
                )),
            );

            $created = $this->gateway->submit($settings, $bundle);
            $encounterId = null;
            foreach ($created as $resource) {
                if ($resource['type'] === 'Encounter') {
                    $encounterId = $resource['id'];
                }
            }
            $this->submissions->markSent($submission->id, $encounterId ?? '-', $this->clock->now());

            return 'sent';
        } catch (SatuSehatFailure $e) {
            if (! $e->retryable) {
                $this->submissions->markFailed($submission->id, $e->getMessage());

                return 'failed';
            }
            $attempt = $submission->attempts + 1;
            if ($attempt >= self::MAX_ATTEMPTS) {
                $this->submissions->markFailed($submission->id, $e->getMessage());

                return 'failed';
            }
            $this->submissions->markRetry($submission->id, $e->getMessage(), $this->after(self::BACKOFF_MINUTES[$attempt - 1] ?? 240));

            return 'retry';
        }
    }

    private function after(int $minutes): DateTimeImmutable
    {
        return $this->clock->now()->add(new DateInterval('PT'.$minutes.'M'));
    }
}
