<?php

declare(strict_types=1);

namespace Src\Lab\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidLabOrder extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }
}
