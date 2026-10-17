<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Src\Integration\Domain\Reminder\ReminderKind;
use Src\Integration\Domain\Reminder\ReminderRecord;
use Src\Integration\Domain\Reminder\ReminderRepository;
use Src\Integration\Domain\Reminder\ReminderStatus;
use Src\Integration\Infrastructure\Eloquent\PatientReminderModel;
use Src\Integration\Infrastructure\Eloquent\ReminderOptOutModel;

final class EloquentReminderRepository implements ReminderRepository
{
    public function enqueue(ReminderKind $kind, string $patientId, ?string $appointmentId, ?string $encounterId, DateTimeImmutable $dueAt): bool
    {
        $exists = PatientReminderModel::withTrashed()->where('kind', $kind->value)
            ->when($appointmentId !== null, fn ($q) => $q->where('appointment_id', $appointmentId))
            ->when($encounterId !== null, fn ($q) => $q->where('encounter_id', $encounterId))->exists();
        if ($exists) {
            return false;
        }

        try {
            (new PatientReminderModel)->forceFill([
                'patient_id' => $patientId, 'appointment_id' => $appointmentId, 'encounter_id' => $encounterId, 'kind' => $kind->value,
                'status' => ReminderStatus::Pending->value, 'due_at' => $dueAt, 'attempts' => 0,
            ])->save();
        } catch (QueryException) {
            // Balapan dua proses: kunci unik sudah dipakai proses lain.
            return false;
        }

        return true;
    }

    public function find(string $id): ?ReminderRecord
    {
        $m = PatientReminderModel::query()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function pendingDue(DateTimeImmutable $now, int $limit): array
    {
        return array_values(PatientReminderModel::query()->where('status', ReminderStatus::Pending->value)->where('due_at', '<=', $now)
            ->orderBy('due_at')->orderBy('id')->limit($limit)->get()->map(fn (PatientReminderModel $m) => $this->record($m))->all());
    }

    public function history(int $limit): array
    {
        return array_values(PatientReminderModel::query()->where('status', '!=', ReminderStatus::Pending->value)
            ->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get()->map(fn (PatientReminderModel $m) => $this->record($m))->all());
    }

    public function markSent(string $id, string $channel, ?string $actorId, DateTimeImmutable $at): void
    {
        $m = PatientReminderModel::query()->findOrFail($id);
        $m->forceFill(['status' => ReminderStatus::Sent->value, 'channel' => $channel, 'sent_at' => $at, 'sent_by' => $actorId, 'attempts' => $m->attempts + 1, 'last_error' => null])->save();
    }

    public function markAttemptFailed(string $id, string $error, int $maxAttempts): void
    {
        $m = PatientReminderModel::query()->findOrFail($id);
        $attempts = $m->attempts + 1;
        $m->forceFill([
            'attempts' => $attempts,
            'last_error' => mb_substr($error, 0, 250),
            'status' => $attempts >= $maxAttempts ? ReminderStatus::Failed->value : ReminderStatus::Pending->value,
        ])->save();
    }

    public function markSkipped(string $id, string $reason): void
    {
        PatientReminderModel::query()->findOrFail($id)->forceFill(['status' => ReminderStatus::Skipped->value, 'last_error' => mb_substr($reason, 0, 250)])->save();
    }

    public function isOptedOut(string $patientId): bool
    {
        return ReminderOptOutModel::query()->where('patient_id', $patientId)->where('is_active', true)->exists();
    }

    public function optedOut(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }

        return array_fill_keys(ReminderOptOutModel::query()->whereIn('patient_id', $patientIds)->where('is_active', true)->pluck('patient_id')->all(), true);
    }

    public function optOut(string $actorId, string $patientId): void
    {
        $row = ReminderOptOutModel::query()->where('patient_id', $patientId)->first() ?? (new ReminderOptOutModel)->forceFill(['patient_id' => $patientId, 'created_by' => $actorId]);
        $row->forceFill(['is_active' => true])->save();
    }

    public function optIn(string $actorId, string $patientId): void
    {
        ReminderOptOutModel::query()->where('patient_id', $patientId)->update(['is_active' => false]);
    }

    public function optOutList(): array
    {
        return array_values(ReminderOptOutModel::query()->where('is_active', true)->orderBy('created_at')->pluck('patient_id')->all());
    }

    private function record(PatientReminderModel $m): ReminderRecord
    {
        return new ReminderRecord(
            $m->id, ReminderKind::from($m->kind), ReminderStatus::from($m->status), $m->patient_id, $m->appointment_id, $m->encounter_id,
            $m->due_at->toDateTimeImmutable(), $m->attempts, $m->last_error, $m->sent_at?->toDateTimeImmutable(), $m->channel,
        );
    }
}
