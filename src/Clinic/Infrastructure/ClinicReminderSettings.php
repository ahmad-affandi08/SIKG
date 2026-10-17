<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ReminderSettings;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

final class ClinicReminderSettings implements ReminderSettings
{
    public function enabled(): bool
    {
        return ClinicModel::query()->first()?->clinicSettings()->remindersEnabled ?? false;
    }
}
