<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidClaim extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }

    public function httpStatus(): int
    {
        return in_array($this->businessCode(), ['claim.not_found', 'policy.not_found'], true) ? 404 : 422;
    }
}
