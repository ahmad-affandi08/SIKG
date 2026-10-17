<?php

declare(strict_types=1);

namespace Src\Clinical\Contracts;

interface EncounterExportLookup
{
    public function find(string $encounterId): ?EncounterExport;
}
