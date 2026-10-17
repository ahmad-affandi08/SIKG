<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use Src\Pharmacy\Contracts\PrescriptionLookup;
use Src\Pharmacy\Contracts\PrescriptionSummary;
use Src\Pharmacy\Domain\PrescriptionRepository;

final readonly class EloquentPrescriptionLookup implements PrescriptionLookup
{
    public function __construct(private PrescriptionRepository $prescriptions) {}

    public function forEncounters(array $encounterIds): array
    {
        $out = [];
        foreach ($encounterIds as $encounterId) {
            foreach ($this->prescriptions->forEncounter($encounterId) as $p) {
                $out[$encounterId][] = new PrescriptionSummary($p->id, $encounterId, $p->status, $p->voidReason, $p->issuedAt->format('c'), array_map(fn ($i) => $i->toArray(), $p->items));
            }
        }

        return $out;
    }
}
