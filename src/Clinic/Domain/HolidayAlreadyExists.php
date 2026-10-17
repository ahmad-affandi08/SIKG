<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

use Src\Shared\Domain\DomainException;

final class HolidayAlreadyExists extends DomainException
{
    public function __construct()
    {
        parent::__construct('holiday.already_exists', 'Tanggal itu sudah terdaftar sebagai hari libur.');
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
