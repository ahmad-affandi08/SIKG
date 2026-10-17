<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinic\Contracts\DoctorSummary;
use Src\Clinic\Infrastructure\Eloquent\DoctorModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorScheduleModel;

final class EloquentDoctorDirectory implements DoctorDirectory
{
    public function find(string $doctorId): ?DoctorSummary
    {
        $d = DoctorModel::query()->find($doctorId);

        return $d === null ? null : $this->summary($d);
    }

    public function active(): array
    {
        return array_values(DoctorModel::query()->where('is_active', true)->orderBy('name')->get()->map(fn (DoctorModel $d) => $this->summary($d))->all());
    }

    public function byIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (DoctorModel::withTrashed()->whereKey(array_values(array_unique($ids)))->get() as $d) {
            $out[$d->id] = $this->summary($d);
        }

        return $out;
    }

    public function weeklySchedule(string $doctorId): array
    {
        return array_values(DoctorScheduleModel::query()->where('doctor_id', $doctorId)->orderBy('weekday')->orderBy('start_time')->get()
            ->map(fn (DoctorScheduleModel $s) => ['weekday' => (int) $s->weekday, 'start' => substr($s->start_time, 0, 5), 'end' => substr($s->end_time, 0, 5)])->all());
    }

    private function summary(DoctorModel $d): DoctorSummary
    {
        return new DoctorSummary($d->id, $d->name, $d->is_active, $d->branch_id, $d->sip_no);
    }
}
