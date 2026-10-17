<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidOrthodontics extends DomainException
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
        return $this->businessCode() === 'ortho.not_found' ? 404 : 422;
    }
}
