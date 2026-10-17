<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use Src\Shared\Domain\DomainException;

final class DuplicatePatientSuspected extends DomainException
{
    public function __construct()
    {
        parent::__construct('patient.duplicate_suspected', 'Ada pasien yang mirip. Periksa dulu, lalu konfirmasi bila memang pasien berbeda.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
