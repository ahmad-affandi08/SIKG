<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use DateTimeImmutable;
use Src\Integration\Domain\SubmissionRecord;
use Src\Integration\Domain\SubmissionRepository;
use Src\Integration\Domain\SubmissionStatus;
use Src\Integration\Infrastructure\Eloquent\SatuSehatSubmissionModel;

final class EloquentSubmissionRepository implements SubmissionRepository
{
    public function enqueue(string $encounterId, string $patientId, DateTimeImmutable $at): bool
    {
        if (SatuSehatSubmissionModel::withTrashed()->where('encounter_id', $encounterId)->exists()) {
            return false;
        }

        (new SatuSehatSubmissionModel)->forceFill([
            'encounter_id' => $encounterId, 'patient_id' => $patientId, 'status' => SubmissionStatus::Pending->value,
            'attempts' => 0, 'next_attempt_at' => $at,
        ])->save();

        return true;
    }

    public function find(string $id): ?SubmissionRecord
    {
        $row = SatuSehatSubmissionModel::query()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function due(DateTimeImmutable $now, int $limit): array
    {
        return array_values(SatuSehatSubmissionModel::query()
            ->where('status', SubmissionStatus::Pending->value)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('created_at')->orderBy('id')->limit($limit)->get()
            ->map(fn (SatuSehatSubmissionModel $m) => $this->record($m))->all());
    }

    public function recent(int $limit): array
    {
        return array_values(SatuSehatSubmissionModel::query()->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (SatuSehatSubmissionModel $m) => $this->record($m))->all());
    }

    public function counts(): array
    {
        return SatuSehatSubmissionModel::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
    }

    public function markSent(string $id, string $encounterIhsId, DateTimeImmutable $at): void
    {
        $this->update($id, ['status' => SubmissionStatus::Sent->value, 'encounter_ihs_id' => $encounterIhsId, 'sent_at' => $at, 'last_error' => null, 'next_attempt_at' => null], true);
    }

    public function markRetry(string $id, string $error, ?DateTimeImmutable $nextAttemptAt): void
    {
        $this->update($id, ['status' => SubmissionStatus::Pending->value, 'last_error' => $this->clip($error), 'next_attempt_at' => $nextAttemptAt], true);
    }

    public function markFailed(string $id, string $error): void
    {
        $this->update($id, ['status' => SubmissionStatus::Failed->value, 'last_error' => $this->clip($error), 'next_attempt_at' => null], true);
    }

    public function markBlocked(string $id, string $reason): void
    {
        $this->update($id, ['status' => SubmissionStatus::Blocked->value, 'last_error' => $this->clip($reason), 'next_attempt_at' => null]);
    }

    public function markSkipped(string $id, string $reason): void
    {
        $this->update($id, ['status' => SubmissionStatus::Skipped->value, 'last_error' => $this->clip($reason), 'next_attempt_at' => null]);
    }

    public function requeue(string $id, DateTimeImmutable $at): void
    {
        $this->update($id, ['status' => SubmissionStatus::Pending->value, 'attempts' => 0, 'last_error' => null, 'next_attempt_at' => $at]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function update(string $id, array $values, bool $countAttempt = false): void
    {
        $row = SatuSehatSubmissionModel::query()->findOrFail($id);
        if ($countAttempt) {
            $values['attempts'] = $row->attempts + 1;
        }
        $row->forceFill($values)->save();
    }

    private function clip(string $text): string
    {
        return mb_substr($text, 0, 250);
    }

    private function record(SatuSehatSubmissionModel $m): SubmissionRecord
    {
        return new SubmissionRecord(
            $m->id, $m->encounter_id, $m->patient_id, SubmissionStatus::from($m->status), $m->attempts,
            $m->next_attempt_at?->toDateTimeImmutable(), $m->last_error, $m->encounter_ihs_id, $m->sent_at?->toDateTimeImmutable(), $m->created_at->toDateTimeImmutable(),
        );
    }
}
