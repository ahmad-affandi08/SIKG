<?php

declare(strict_types=1);

namespace Src\Integration\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidSatuSehatSettings extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct('satusehat.invalid_settings', $message);
    }
}
