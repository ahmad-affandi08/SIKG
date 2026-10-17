<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use Src\Shared\Domain\DomainException;

final class AppointmentConflict extends DomainException
{
    /**
     * @param  'doctor'|'chair'|'patient'  $resource
     */
    public function __construct(public readonly string $resource)
    {
        parent::__construct('appointment.conflict', match ($resource) {
            'doctor' => 'Dokter sudah memiliki janji pada jam itu.',
            'chair' => 'Kursi sudah dipakai pada jam itu.',
            default => 'Pasien sudah memiliki janji lain yang bertumpuk pada jam itu.',
        });
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
