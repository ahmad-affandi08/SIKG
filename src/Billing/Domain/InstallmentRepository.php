<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

interface InstallmentRepository
{
    public function forInvoice(string $invoiceId): ?InstallmentPlan;

    /**
     * @param  list<string>  $invoiceIds
     * @return array<string, InstallmentPlan> id tagihan => jadwal
     */
    public function forInvoices(array $invoiceIds): array;

    public function save(InstallmentPlan $plan, string $actorId): void;
}
