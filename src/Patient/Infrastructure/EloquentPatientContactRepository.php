<?php

declare(strict_types=1);

namespace Src\Patient\Infrastructure;

use DateTimeImmutable;
use Src\Patient\Domain\ContactRecord;
use Src\Patient\Domain\PatientContactRepository;
use Src\Patient\Infrastructure\Eloquent\PatientContactModel;
use Src\Shared\Application\PiiProtector;
use Src\Shared\Infrastructure\Tenancy\ClinicScope;

final readonly class EloquentPatientContactRepository implements PatientContactRepository
{
    public function __construct(private PiiProtector $pii) {}

    public function find(string $patientId): ?ContactRecord
    {
        $row = PatientContactModel::query()->where('patient_id', $patientId)->first();

        return $row === null ? null : $this->record($row);
    }

    public function forPatients(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        $list = [];
        foreach (PatientContactModel::query()->whereIn('patient_id', $patientIds)->get() as $row) {
            $list[$row->patient_id] = $this->record($row);
        }

        return $list;
    }

    public function saveContact(string $actorId, string $patientId, ?string $email, bool $optIn, ?DateTimeImmutable $optInAt): void
    {
        $row = $this->row($patientId);
        $row->forceFill([
            'email_encrypted' => $email === null ? null : $this->pii->encrypt($email),
            'reminders_opt_in' => $optIn,
            'opt_in_at' => $optIn ? ($row->opt_in_at ?? $optInAt) : null,
            'updated_by' => $actorId,
        ])->save();
    }

    public function setLink(string $actorId, string $patientId, ?string $hash, ?DateTimeImmutable $expiresAt): void
    {
        $row = $this->row($patientId);
        $row->forceFill(['telegram_link_hash' => $hash, 'telegram_link_expires_at' => $expiresAt, 'updated_by' => $actorId])->save();
    }

    public function findByLink(string $hash, DateTimeImmutable $now): ?array
    {
        $row = PatientContactModel::withoutGlobalScope(ClinicScope::class)
            ->where('telegram_link_hash', $hash)
            ->where('telegram_link_expires_at', '>', $now)
            ->first();

        return $row === null ? null : ['clinic_id' => $row->clinic_id, 'patient_id' => $row->patient_id];
    }

    public function attachTelegram(string $patientId, string $chatId): void
    {
        $row = $this->row($patientId);
        $row->forceFill([
            'telegram_chat_id_encrypted' => $this->pii->encrypt($chatId),
            'telegram_link_hash' => null,
            'telegram_link_expires_at' => null,
        ])->save();
    }

    public function detachTelegram(string $actorId, string $patientId): void
    {
        $row = $this->row($patientId);
        $row->forceFill(['telegram_chat_id_encrypted' => null, 'telegram_link_hash' => null, 'telegram_link_expires_at' => null, 'updated_by' => $actorId])->save();
    }

    private function row(string $patientId): PatientContactModel
    {
        return PatientContactModel::query()->where('patient_id', $patientId)->first() ?? (new PatientContactModel)->forceFill(['patient_id' => $patientId, 'reminders_opt_in' => false]);
    }

    private function record(PatientContactModel $row): ContactRecord
    {
        return new ContactRecord(
            $row->patient_id,
            $row->email_encrypted === null ? null : $this->pii->decrypt($row->email_encrypted),
            $row->telegram_chat_id_encrypted === null ? null : $this->pii->decrypt($row->telegram_chat_id_encrypted),
            $row->reminders_opt_in,
            $row->opt_in_at === null ? null : $row->opt_in_at->toDateTimeImmutable(),
            $row->telegram_link_hash !== null,
        );
    }
}
