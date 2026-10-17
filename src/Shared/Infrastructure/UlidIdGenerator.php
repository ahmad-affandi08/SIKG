<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Src\Shared\Domain\IdGenerator;
use Symfony\Component\Uid\Ulid;

final class UlidIdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return strtoupper((new Ulid)->toBase32());
    }
}
