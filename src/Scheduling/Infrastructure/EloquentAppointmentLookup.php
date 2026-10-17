<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure;

use Src\Scheduling\Contracts\AppointmentInfo;
use Src\Scheduling\Contracts\AppointmentLookup;
use Src\Scheduling\Infrastructure\Eloquent\AppointmentModel;

final class EloquentAppointmentLookup implements AppointmentLookup
{
    public function find(string $appointmentId): ?AppointmentInfo
    {
        $a = AppointmentModel::query()->find($appointmentId);

        return $a === null ? null : new AppointmentInfo($a->id, $a->patient_id, $a->doctor_id, $a->chair_id, $a->status, $a->starts_at->toDateTimeImmutable());
    }
}
