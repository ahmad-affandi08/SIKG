<?php

declare(strict_types=1);

namespace Src\Integration\Application;

use Src\Clinic\Contracts\DoctorDirectory;
use Src\Integration\Domain\IdentifierRepository;
use Src\Integration\Domain\InvalidSatuSehatSettings;
use Src\Integration\Domain\SatuSehatEnvironment;
use Src\Integration\Domain\SatuSehatFailure;
use Src\Integration\Domain\SatuSehatSettingsRepository;
use Src\Integration\Domain\SubmissionRepository;
use Src\Integration\Domain\SubmissionStatus;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * P2-01: pengaturan koneksi SATUSEHAT, NIK dokter, dan pemantauan pengiriman. Kredensial hanya masuk (tulis saja) dan tidak
 * pernah dikembalikan ke layar; audit mencatat perubahan tanpa rahasia dan tanpa NIK.
 */
final readonly class SatuSehatAdministration
{
    public function __construct(
        private SatuSehatSettingsRepository $settings,
        private IdentifierRepository $identifiers,
        private SubmissionRepository $submissions,
        private SatuSehatGateway $gateway,
        private DoctorDirectory $doctors,
        private PatientLookup $patients,
        private AuditLogger $audit,
        private Transaction $tx,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $settings = $this->settings->get();
        $statuses = $this->identifiers->practitionerStatuses();
        $recent = $this->submissions->recent(50);
        $patients = $this->patients->summaries(array_values(array_unique(array_map(fn ($s) => $s->patientId, $recent))));

        return [
            'settings' => [
                'enabled' => $settings->enabled,
                'environment' => $settings->environment->value,
                'organization_id' => $settings->organizationId,
                'location_id' => $settings->locationId,
                'location_name' => $settings->locationName,
                'client_id' => $settings->clientId,
                'has_secret' => $this->settings->hasSecret(),
                'complete' => $settings->isComplete(),
            ],
            'environments' => array_map(fn (SatuSehatEnvironment $e) => ['value' => $e->value, 'label' => $e->label()], SatuSehatEnvironment::cases()),
            'doctors' => array_map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'has_nik' => $statuses[$d->id]['has_nik'] ?? false,
                'ihs_id' => $statuses[$d->id]['ihs_id'] ?? null,
            ], $this->doctors->active()),
            'counts' => $this->counts(),
            'submissions' => array_map(fn ($s) => [
                'id' => $s->id,
                'encounter_id' => $s->encounterId,
                'medical_record_no' => $patients[$s->patientId]->medicalRecordNo ?? null,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'attempts' => $s->attempts,
                'last_error' => $s->lastError,
                'created_at' => $s->createdAt->format('c'),
                'sent_at' => $s->sentAt?->format('c'),
                'can_retry' => in_array($s->status, [SubmissionStatus::Failed, SubmissionStatus::Blocked, SubmissionStatus::Skipped], true),
            ], $recent),
        ];
    }

    /**
     * @throws InvalidSatuSehatSettings
     */
    public function saveSettings(string $actorId, bool $enabled, string $environment, ?string $organizationId, ?string $locationId, ?string $locationName, ?string $clientId, ?string $clientSecret): void
    {
        $env = SatuSehatEnvironment::tryFrom($environment) ?? throw new InvalidSatuSehatSettings('Lingkungan SATUSEHAT tidak dikenal.');
        $clean = fn (?string $v) => $v === null || trim($v) === '' ? null : trim($v);
        [$organizationId, $locationId, $locationName, $clientId, $clientSecret] = [$clean($organizationId), $clean($locationId), $clean($locationName), $clean($clientId), $clean($clientSecret)];

        $hasSecret = $clientSecret !== null || $this->settings->hasSecret();
        if ($enabled && ($organizationId === null || $locationId === null || $clientId === null || ! $hasSecret)) {
            throw new InvalidSatuSehatSettings('Untuk mengaktifkan integrasi, isi ID organisasi, ID lokasi, client ID, dan client secret.');
        }

        $this->tx->run(function () use ($actorId, $enabled, $env, $organizationId, $locationId, $locationName, $clientId, $clientSecret): void {
            $before = $this->settings->get();
            $this->settings->save($actorId, $enabled, $env, $organizationId, $locationId, $locationName, $clientId, $clientSecret);
            $this->audit->record(new AuditEntry(
                $actorId, 'satusehat.settings_updated', 'satusehat_settings', $actorId,
                ['enabled' => $before->enabled, 'environment' => $before->environment->value, 'organization_id' => $before->organizationId],
                ['enabled' => $enabled, 'environment' => $env->value, 'organization_id' => $organizationId, 'secret_changed' => $clientSecret !== null],
            ));
        });
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(string $actorId): array
    {
        $settings = $this->settings->get();
        if (! $settings->isComplete()) {
            return ['ok' => false, 'message' => 'Lengkapi ID organisasi, ID lokasi, client ID, dan client secret lebih dulu.'];
        }

        try {
            $this->gateway->verify($settings);
        } catch (SatuSehatFailure $e) {
            $this->audit->record(new AuditEntry($actorId, 'satusehat.connection_failed', 'satusehat_settings', $actorId));

            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $this->audit->record(new AuditEntry($actorId, 'satusehat.connection_ok', 'satusehat_settings', $actorId));

        return ['ok' => true, 'message' => 'Koneksi ke SATUSEHAT berhasil.'];
    }

    /**
     * @throws InvalidSatuSehatSettings
     */
    public function savePractitionerNik(string $actorId, string $doctorId, ?string $nik): void
    {
        $this->doctors->find($doctorId) ?? throw new InvalidSatuSehatSettings('Dokter tidak ditemukan.');
        $digits = $nik === null ? '' : preg_replace('/\s+/', '', $nik);
        if ($digits !== '' && preg_match('/^\d{16}$/', (string) $digits) !== 1) {
            throw new InvalidSatuSehatSettings('NIK dokter harus 16 digit angka.');
        }

        $this->tx->run(function () use ($actorId, $doctorId, $digits): void {
            $this->identifiers->savePractitionerNik($actorId, $doctorId, $digits === '' ? null : (string) $digits);
            // NIK tidak dicatat di audit; hanya fakta bahwa NIK dokter diubah.
            $this->audit->record(new AuditEntry($actorId, 'satusehat.practitioner_nik_updated', 'doctor', $doctorId, null, ['has_nik' => $digits !== '']));
        });
    }

    /**
     * @throws InvalidSatuSehatSettings
     */
    public function retry(string $actorId, string $submissionId): void
    {
        $submission = $this->submissions->find($submissionId) ?? throw new InvalidSatuSehatSettings('Pengiriman tidak ditemukan.');
        if ($submission->status === SubmissionStatus::Sent || $submission->status === SubmissionStatus::Pending) {
            throw new InvalidSatuSehatSettings('Pengiriman ini tidak perlu diulang.');
        }

        $this->tx->run(function () use ($actorId, $submission): void {
            $this->submissions->requeue($submission->id, $this->clock->now());
            $this->audit->record(new AuditEntry($actorId, 'satusehat.submission_requeued', 'encounter', $submission->encounterId));
        });
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = $this->submissions->counts();
        $out = [];
        foreach (SubmissionStatus::cases() as $status) {
            $out[$status->value] = $counts[$status->value] ?? 0;
        }

        return $out;
    }
}
