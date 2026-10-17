<?php

declare(strict_types=1);

namespace Src\Clinical\Infrastructure;

use Src\Clinical\Contracts\EncounterExport;
use Src\Clinical\Contracts\EncounterExportLookup;
use Src\Clinical\Domain\EncounterRepository;

final readonly class EloquentEncounterExportLookup implements EncounterExportLookup
{
    public function __construct(private EncounterRepository $encounters) {}

    public function find(string $encounterId): ?EncounterExport
    {
        $encounter = $this->encounters->find($encounterId);
        if ($encounter === null) {
            return null;
        }

        return new EncounterExport(
            $encounter->id,
            $encounter->patientId,
            $encounter->doctorId,
            $encounter->startedAt,
            $encounter->closedAt,
            $encounter->isClosed(),
            array_map(fn ($d) => ['code' => $d->icd10Code, 'name' => $d->name], $this->encounters->diagnoses($encounterId)),
            array_map(fn ($p) => ['procedure_id' => $p->procedureId, 'code' => $p->code, 'name' => $p->name], $this->encounters->procedures($encounterId)),
        );
    }
}
