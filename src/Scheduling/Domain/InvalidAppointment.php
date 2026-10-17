<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Janji yang melanggar aturan (di luar jadwal praktik, hari libur, di masa lalu, alasan kosong, dan sebagainya).
 */
final class InvalidAppointment extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }
}
