<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ServiceProfileLookup;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Shared\Domain\ServiceProfile;

final class ClinicServiceProfileLookup implements ServiceProfileLookup
{
    /** Tanpa konteks klinik, bawaan aman adalah profil yang tidak menutup apa pun yang sudah ada. */
    public function current(): ServiceProfile
    {
        $clinic = ClinicModel::query()->first();

        return $clinic instanceof ClinicModel ? $clinic->clinicSettings()->serviceProfile : ServiceProfile::default();
    }
}
