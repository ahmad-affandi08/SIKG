<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\DiscountLimits;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

final class ClinicDiscountLimits implements DiscountLimits
{
    public function basisPointsFor(array $roles): int
    {
        $limits = ClinicModel::query()->firstOrFail()->clinicSettings()->discountLimits;
        $best = 0;
        foreach ($roles as $role) {
            $best = max($best, ($limits[$role] ?? 0) * 100);
        }

        return $best;
    }
}
