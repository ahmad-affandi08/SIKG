<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\TaxRate;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

final class ClinicTaxRate implements TaxRate
{
    public function basisPoints(): int
    {
        return ClinicModel::query()->firstOrFail()->clinicSettings()->taxPercent * 100;
    }
}
