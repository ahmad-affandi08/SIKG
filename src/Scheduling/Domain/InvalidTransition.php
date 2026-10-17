<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidTransition extends DomainException
{
    public function __construct(AppointmentStatus $from, AppointmentStatus $to)
    {
        parent::__construct('appointment.invalid_transition', "Janji berstatus {$from->label()} tidak dapat diubah menjadi {$to->label()}.");
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
