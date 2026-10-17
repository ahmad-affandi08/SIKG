<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

use Src\Shared\Domain\DomainException;

/**
 * Catatan klinis terkunci (M6, RM-07): kunjungan sudah ditutup atau batas koreksi 2x24 jam terlewati.
 */
final class EncounterLocked extends DomainException
{
    public function __construct(string $message = 'Kunjungan sudah ditutup. Perubahan hanya lewat addendum.')
    {
        parent::__construct('encounter.locked', $message);
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
