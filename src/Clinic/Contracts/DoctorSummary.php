<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

final readonly class DoctorSummary
{
    public function __construct(public string $id, public string $name, public bool $active, public ?string $branchId = null, public ?string $sipNo = null) {}
}
