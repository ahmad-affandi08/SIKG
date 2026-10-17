<?php

declare(strict_types=1);

namespace Src\Clinical\Application\Diagnosis;

final readonly class DiagnosisSummary
{
    public function __construct(public string $id, public string $code, public string $name) {}
}
