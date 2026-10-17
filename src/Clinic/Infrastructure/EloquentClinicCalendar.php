<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Clinic\Infrastructure\Eloquent\HolidayModel;

final class EloquentClinicCalendar implements ClinicCalendar
{
    public function timezone(): string
    {
        return ClinicModel::query()->firstOrFail()->timezone;
    }

    public function isHoliday(string $date): bool
    {
        return HolidayModel::query()->whereDate('date', $date)->exists();
    }
}
