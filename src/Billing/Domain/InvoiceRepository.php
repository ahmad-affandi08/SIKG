<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

interface InvoiceRepository
{
    public function find(string $id): ?Invoice;

    /**
     * Membaca dengan SELECT ... FOR UPDATE; dipakai di dalam transaksi use case.
     */
    public function lockById(string $id): ?Invoice;

    public function lockByEncounter(string $encounterId): ?Invoice;

    public function save(Invoice $invoice, ?string $actorId): void;
}
