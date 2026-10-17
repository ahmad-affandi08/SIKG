<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

final readonly class EncounterInfo
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $doctorId,
        public bool $open,
    ) {}
}
