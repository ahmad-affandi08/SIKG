<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use Src\Billing\Application\EncounterCharges;
use Src\Billing\Contracts\EncounterChargeGateway;

final readonly class BillingEncounterChargeGateway implements EncounterChargeGateway
{
    public function __construct(private EncounterCharges $charges) {}

    public function addCharge(string $encounterId, string $patientId, string $sourceType, string $sourceId, string $description, int $amount): string
    {
        return $this->charges->add($encounterId, $patientId, $sourceType, $sourceId, $description, $amount);
    }
}
