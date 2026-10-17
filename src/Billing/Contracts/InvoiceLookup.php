<?php

declare(strict_types=1);

namespace Src\Billing\Contracts;

interface InvoiceLookup
{
    public function forEncounter(string $encounterId): ?InvoiceSummary;
}
