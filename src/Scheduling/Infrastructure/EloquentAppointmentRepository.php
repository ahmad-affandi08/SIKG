<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Src\Scheduling\Domain\AppointmentNotFound;
use Src\Scheduling\Domain\AppointmentRecord;
use Src\Scheduling\Domain\AppointmentRepository;
use Src\Scheduling\Domain\AppointmentStatus;
use Src\Scheduling\Domain\TimeSlot;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;

final class EloquentAppointmentRepository implements AppointmentRepository
{
    public function add(
        string $actorId,
        string $patientId,
        string $doctorId,
        ?string $chairId,
        TimeSlot $slot,
        AppointmentStatus $status,
        string $source,
        ?string $complaint,
        ?int $queueNo = null,
        ?string $queueDate = null,
        ?DateTimeImmutable $checkedInAt = null,
    ): string {
        $row = new AppointmentModel;
        $row->forceFill([
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'chair_id' => $chairId,
            'starts_at' => $slot->start,
            'ends_at' => $slot->end,
            'status' => $status->value,
            'source' => $source,
            'complaint' => $complaint,
            'queue_no' => $queueNo,
            'queue_date' => $queueDate,
            'checked_in_at' => $checkedInAt,
            'version' => 1,
            'created_by' => $actorId,
        ])->save();

        return $row->id;
    }

    public function find(string $id): ?AppointmentRecord
    {
        $row = AppointmentModel::query()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function findForUpdate(string $id): ?AppointmentRecord
    {
        $row = AppointmentModel::query()->lockForUpdate()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function reschedule(string $actorId, string $id, string $doctorId, ?string $chairId, TimeSlot $slot, string $reason): void
    {
        $row = AppointmentModel::query()->find($id) ?? throw new AppointmentNotFound;
        $row->forceFill([
            'doctor_id' => $doctorId,
            'chair_id' => $chairId,
            'starts_at' => $slot->start,
            'ends_at' => $slot->end,
            'status' => AppointmentStatus::Scheduled->value,
            'reschedule_reason' => $reason,
            'version' => $row->version + 1,
            'updated_by' => $actorId,
        ])->save();
    }

    public function changeStatus(string $actorId, string $id, AppointmentStatus $status, ?string $reason = null): void
    {
        $row = AppointmentModel::query()->find($id) ?? throw new AppointmentNotFound;
        $row->forceFill([
            'status' => $status->value,
            'cancel_reason' => $status === AppointmentStatus::Cancelled ? $reason : $row->cancel_reason,
            'version' => $row->version + 1,
            'updated_by' => $actorId,
        ])->save();
    }

    public function checkIn(string $actorId, string $id, int $queueNo, string $queueDate, DateTimeImmutable $at): void
    {
        $row = AppointmentModel::query()->find($id) ?? throw new AppointmentNotFound;
        $row->forceFill([
            'status' => AppointmentStatus::CheckedIn->value,
            'queue_no' => $queueNo,
            'queue_date' => $queueDate,
            'checked_in_at' => $at,
            'version' => $row->version + 1,
            'updated_by' => $actorId,
        ])->save();
    }

    public function assignChair(string $actorId, string $id, string $chairId): void
    {
        $row = AppointmentModel::query()->find($id) ?? throw new AppointmentNotFound;
        $row->forceFill(['chair_id' => $chairId, 'updated_by' => $actorId])->save();
    }

    public function findConflict(string $doctorId, ?string $chairId, string $patientId, TimeSlot $slot, ?string $ignoreId = null): ?string
    {
        $overlapping = fn (): Builder => AppointmentModel::query()
            ->whereIn('status', AppointmentStatus::occupyingValues())
            ->where('source', 'booking')
            ->where('starts_at', '<', $slot->end)
            ->where('ends_at', '>', $slot->start)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId));

        if ($overlapping()->where('doctor_id', $doctorId)->exists()) {
            return 'doctor';
        }
        if ($chairId !== null && $overlapping()->where('chair_id', $chairId)->exists()) {
            return 'chair';
        }
        if ($overlapping()->where('patient_id', $patientId)->exists()) {
            return 'patient';
        }

        return null;
    }

    public function between(DateTimeImmutable $from, DateTimeImmutable $to, ?string $doctorId = null, ?string $chairId = null): array
    {
        return array_values(AppointmentModel::query()
            ->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->when($doctorId !== null, fn (Builder $q) => $q->where('doctor_id', $doctorId))
            ->when($chairId !== null, fn (Builder $q) => $q->where('chair_id', $chairId))
            ->orderBy('starts_at')->orderBy('id')
            ->get()->map(fn (AppointmentModel $m) => $this->record($m))->all());
    }

    public function queue(string $queueDate): array
    {
        return array_values(AppointmentModel::query()
            ->where('queue_date', $queueDate)->whereNotNull('queue_no')
            ->orderBy('queue_no')
            ->get()->map(fn (AppointmentModel $m) => $this->record($m))->all());
    }

    private function record(AppointmentModel $m): AppointmentRecord
    {
        return new AppointmentRecord(
            $m->id,
            $m->patient_id,
            $m->doctor_id,
            $m->chair_id,
            $m->starts_at->toDateTimeImmutable(),
            $m->ends_at->toDateTimeImmutable(),
            AppointmentStatus::from($m->status),
            $m->source,
            $m->queue_no,
            $m->queue_date?->toDateString(),
            $m->complaint,
            $m->cancel_reason,
            $m->version,
        );
    }
}
