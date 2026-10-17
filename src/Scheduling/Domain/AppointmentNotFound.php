<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use Src\Shared\Domain\DomainException;

final class AppointmentNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('appointment.not_found', 'Janji tidak ditemukan.');
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
