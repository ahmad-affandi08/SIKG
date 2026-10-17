<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use Src\Shared\Domain\DomainException;

final class PatientNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('patient.not_found', 'Pasien tidak ditemukan.');
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
