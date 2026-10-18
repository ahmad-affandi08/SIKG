<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Contracts\ClinicProvisioner;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;

final class EloquentClinicProvisioner implements ClinicProvisioner
{
    public function anyClinicExists(): bool
    {
        return ClinicModel::query()->withoutGlobalScopes()->exists();
    }

    public function create(string $name, string $timezone): string
    {
        $clinic = new ClinicModel;
        $clinic->forceFill(['name' => $name, 'timezone' => $timezone])->save();

        return (string) $clinic->id;
    }
}
