<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use Src\Billing\Application\InvoiceService;
use Src\Billing\Contracts\InvoiceClaimGateway;
use Src\Billing\Contracts\InvoiceClaimInfo;
use Src\Billing\Domain\InvoiceStatus;
use Src\Billing\Domain\PaymentType;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;

final readonly class BillingInvoiceClaimGateway implements InvoiceClaimGateway
{
    public function __construct(private InvoiceService $invoices) {}

    public function find(string $invoiceId): ?InvoiceClaimInfo
    {
        $row = InvoiceModel::query()->find($invoiceId);

        return $row === null ? null : $this->info($row);
    }

    public function byIds(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }
        $out = [];
        foreach (InvoiceModel::withTrashed()->whereKey(array_values(array_unique($invoiceIds)))->get() as $row) {
            $out[$row->id] = $this->info($row);
        }

        return $out;
    }

    public function recordPayment(string $actorId, string $invoiceId, string $methodId, int $amount, string $idempotencyKey, ?string $referenceNo): void
    {
        $this->invoices->recordPayment($actorId, $invoiceId, $methodId, PaymentType::Pay, $amount, $idempotencyKey, $referenceNo);
    }

    private function info(InvoiceModel $row): InvoiceClaimInfo
    {
        $status = InvoiceStatus::tryFrom($row->status);

        return new InvoiceClaimInfo($row->id, $row->number, $row->patient_id, $row->status, $status?->label() ?? $row->status, (int) $row->total_amount, (int) $row->paid_amount);
    }
}
