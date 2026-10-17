<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use DateTimeImmutable;

final readonly class ConsentRecord
{
    public function __construct(public string $version, public DateTimeImmutable $givenAt, public string $recordedBy) {}
}
