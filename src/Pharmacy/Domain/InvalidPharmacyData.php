<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidPharmacyData extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }
}
