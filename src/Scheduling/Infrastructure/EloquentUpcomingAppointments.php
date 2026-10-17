<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure;

use DateTimeImmutable;
use Src\Scheduling\Contracts\AppointmentInfo;
use Src\Scheduling\Contracts\UpcomingAppointments;
use Src\Scheduling\Domain\AppointmentStatus;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;

final class EloquentUpcomingAppointments implements UpcomingAppointments
{
    private const OPEN = [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value];

    public function between(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return array_values(AppointmentModel::query()->whereIn('status', self::OPEN)->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->orderBy('starts_at')->orderBy('id')->get()
            ->map(fn (AppointmentModel $a) => new AppointmentInfo($a->id, $a->patient_id, $a->doctor_id, $a->chair_id, $a->status, $a->starts_at->toDateTimeImmutable()))->all());
    }

    public function patientsWithAppointmentAfter(array $patientIds, DateTimeImmutable $after): array
    {
        if ($patientIds === []) {
            return [];
        }

        return array_values(AppointmentModel::query()->whereIn('patient_id', $patientIds)->whereIn('status', self::OPEN)->where('starts_at', '>', $after)
            ->distinct()->pluck('patient_id')->all());
    }
}
