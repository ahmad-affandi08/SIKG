<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Application\Doctors\DoctorAdministration;
use Src\Clinic\Application\Doctors\DoctorData;
use Src\Clinic\Domain\FeeScheme;
use Src\Clinic\Domain\InvalidDoctorData;
use Src\Clinic\Infrastructure\Eloquent\BranchModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorScheduleModel;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

final readonly class EloquentDoctorAdministration implements DoctorAdministration
{
    public function __construct(private Transaction $tx, private AuditLogger $audit, private UserLookup $users) {}

    public function list(): array
    {
        $branches = BranchModel::query()->pluck('name', 'id');
        $users = collect($this->users->activeDoctors())->pluck('name', 'id');
        $counts = DoctorScheduleModel::query()->selectRaw('doctor_id, count(*) as total')->groupBy('doctor_id')->pluck('total', 'doctor_id');

        return array_values(DoctorModel::query()->orderBy('name')->get()->map(fn (DoctorModel $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'sip_no' => $d->sip_no,
            'specialty' => $d->specialty,
            'branch' => $d->branch_id === null ? null : ($branches[$d->branch_id] ?? null),
            'user' => $d->user_id === null ? null : ($users[$d->user_id] ?? null),
            'is_active' => $d->is_active,
            'schedule_count' => (int) ($counts[$d->id] ?? 0),
        ])->all());
    }

    public function find(string $id): array
    {
        $doctor = DoctorModel::query()->findOrFail($id);

        return [
            'id' => $doctor->id,
            'name' => $doctor->name,
            'sip_no' => $doctor->sip_no,
            'specialty' => $doctor->specialty,
            'branch_id' => $doctor->branch_id,
            'user_id' => $doctor->user_id,
            'is_active' => $doctor->is_active,
            'fee_scheme' => FeeScheme::fromArray($doctor->fee_scheme)->toArray(),
            'schedule' => $this->schedule($doctor->id),
        ];
    }

    public function create(string $actorId, DoctorData $data): string
    {
        return $this->tx->run(function () use ($actorId, $data): string {
            $this->guard($data, null);

            $doctor = new DoctorModel;
            $doctor->forceFill($this->attributes($data) + ['is_active' => true, 'created_by' => $actorId])->save();
            $this->replaceSchedule($doctor->id, $data);

            $this->audit->record(new AuditEntry($actorId, 'doctor.created', 'doctor', $doctor->id, null, $this->snapshot($doctor->id)));

            return $doctor->id;
        });
    }

    public function update(string $actorId, string $id, DoctorData $data): void
    {
        $this->tx->run(function () use ($actorId, $id, $data): void {
            $doctor = DoctorModel::query()->lockForUpdate()->findOrFail($id);
            $this->guard($data, $id);
            $before = $this->snapshot($id);

            $doctor->forceFill($this->attributes($data) + ['updated_by' => $actorId])->save();
            $this->replaceSchedule($id, $data);

            $this->audit->record(new AuditEntry($actorId, 'doctor.updated', 'doctor', $id, $before, $this->snapshot($id)));
        });
    }

    public function setActive(string $actorId, string $id, bool $active): void
    {
        $this->tx->run(function () use ($actorId, $id, $active): void {
            $doctor = DoctorModel::query()->lockForUpdate()->findOrFail($id);
            $doctor->forceFill(['is_active' => $active, 'updated_by' => $actorId])->save();

            $this->audit->record(new AuditEntry($actorId, $active ? 'doctor.activated' : 'doctor.deactivated', 'doctor', $id, ['is_active' => ! $active], ['is_active' => $active]));
        });
    }

    private function guard(DoctorData $data, ?string $ignoreId): void
    {
        if ($data->branchId !== null && ! BranchModel::query()->whereKey($data->branchId)->exists()) {
            throw new InvalidDoctorData('doctor.invalid_branch', 'Cabang tidak ditemukan.');
        }
        if ($data->userId === null) {
            return;
        }
        if (! $this->users->isActiveDoctor($data->userId)) {
            throw new InvalidDoctorData('doctor.invalid_user', 'Akun yang ditautkan harus pengguna aktif berperan Dokter.');
        }
        $taken = DoctorModel::query()->where('user_id', $data->userId)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
        if ($taken) {
            throw new InvalidDoctorData('doctor.user_already_linked', 'Akun itu sudah ditautkan ke dokter lain.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(DoctorData $data): array
    {
        return [
            'name' => $data->name,
            'sip_no' => $data->sipNo,
            'specialty' => $data->specialty,
            'branch_id' => $data->branchId,
            'user_id' => $data->userId,
            'fee_scheme' => $data->feeScheme->toArray(),
        ];
    }

    private function replaceSchedule(string $doctorId, DoctorData $data): void
    {
        $new = $data->schedule->toArray();
        if ($new === $this->schedule($doctorId)) {
            return;
        }

        DoctorScheduleModel::query()->where('doctor_id', $doctorId)->delete();
        foreach ($new as $slot) {
            (new DoctorScheduleModel)->forceFill([
                'doctor_id' => $doctorId,
                'weekday' => $slot['weekday'],
                'start_time' => $slot['start'].':00',
                'end_time' => $slot['end'].':00',
            ])->save();
        }
    }

    /**
     * @return list<array{weekday: int, start: string, end: string}>
     */
    private function schedule(string $doctorId): array
    {
        return array_values(DoctorScheduleModel::query()->where('doctor_id', $doctorId)
            ->orderBy('weekday')->orderBy('start_time')->get()
            ->map(fn (DoctorScheduleModel $s) => [
                'weekday' => (int) $s->weekday,
                'start' => substr($s->start_time, 0, 5),
                'end' => substr($s->end_time, 0, 5),
            ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(string $doctorId): array
    {
        $d = DoctorModel::query()->findOrFail($doctorId);

        return [
            'name' => $d->name,
            'sip_no' => $d->sip_no,
            'specialty' => $d->specialty,
            'branch_id' => $d->branch_id,
            'user_id' => $d->user_id,
            'fee_scheme' => FeeScheme::fromArray($d->fee_scheme)->toArray(),
            'schedule' => $this->schedule($doctorId),
        ];
    }
}
