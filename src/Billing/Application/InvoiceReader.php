<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceRepository;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\IdentityAccess\Contracts\UserLookup;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;

/**
 * Detail tagihan untuk layar kasir dan cetakan. Membuka tagihan (memuat nama pasien) tercatat di access log (M10).
 */
final readonly class InvoiceReader
{
    public function __construct(
        private InvoiceRepository $invoices,
        private PatientLookup $patients,
        private PaymentMethodCatalog $methods,
        private UserLookup $users,
        private AccessLogger $access,
    ) {}

    /**
     * @return array{invoice: array<string, mixed>, items: list<array<string, mixed>>, payments: list<array<string, mixed>>}
     */
    public function detail(string $invoiceId, string $route = 'invoices.show'): array
    {
        $invoice = $this->invoices->find($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
        $this->access->patientRecordOpened($invoice->patientId, $route);

        return $this->present($invoice);
    }

    /**
     * @return array{invoice: array<string, mixed>, items: list<array<string, mixed>>, payments: list<array<string, mixed>>}
     */
    public function present(Invoice $invoice): array
    {
        $patient = $this->patients->summaries([$invoice->patientId])[$invoice->patientId] ?? null;
        $methodIds = array_values(array_unique(array_map(fn ($p) => $p->methodId, $invoice->payments())));
        $methods = $this->methods->byIds($methodIds);
        $users = $this->users->names(array_values(array_unique(array_filter([...array_map(fn ($p) => $p->receivedBy, $invoice->payments()), $invoice->voidedBy()]))));
        $discount = $invoice->discount();

        return [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number(),
                'lifecycle' => $invoice->lifecycle(),
                'status' => $invoice->status()->value,
                'status_label' => $invoice->status()->label(),
                'patient_id' => $invoice->patientId,
                'patient_name' => $patient?->name,
                'medical_record_no' => $patient?->medicalRecordNo,
                'encounter_id' => $invoice->encounterId,
                'subtotal_amount' => $invoice->subtotal()->amount,
                'discount_amount' => $invoice->discountAmount()->amount,
                'discount_type' => $discount?->kind->value,
                'discount_value' => $discount?->value,
                'discount_reason' => $discount?->reason,
                'total_amount' => $invoice->total()->amount,
                'paid_amount' => $invoice->paid()->amount,
                'balance_amount' => $invoice->balance()->amount,
                'issued_at' => $invoice->issuedAt()?->format('c'),
                'voided_at' => $invoice->voidedAt()?->format('c'),
                'void_reason' => $invoice->voidReason(),
                'voided_by_name' => $invoice->voidedBy() === null ? null : ($users[$invoice->voidedBy()] ?? null),
                'editable' => $invoice->isEditable(),
            ],
            'items' => array_map(fn ($item) => [
                'id' => $item->id,
                'source_type' => $item->sourceType,
                'description' => $item->description,
                'quantity' => $item->quantity(),
                'unit_price_amount' => $item->unitPrice->amount,
                'gross_amount' => $item->gross()->amount,
                'discount_amount' => $item->discountAmount()->amount,
                'discount_type' => $item->discount()?->kind->value,
                'discount_value' => $item->discount()?->value,
                'discount_reason' => $item->discount()?->reason,
                'total_amount' => $item->total()->amount,
                'manual' => $item->isManual(),
            ], $invoice->items()),
            'payments' => array_map(fn ($payment) => [
                'id' => $payment->id,
                'type' => $payment->type->value,
                'type_label' => $payment->type->label(),
                'method_id' => $payment->methodId,
                'method_name' => ($methods[$payment->methodId] ?? null)?->name,
                'amount' => $payment->amount->amount,
                'paid_at' => $payment->paidAt->format('c'),
                'received_by_name' => $users[$payment->receivedBy] ?? null,
                'reference_no' => $payment->referenceNo,
                'receipt_no' => $payment->receiptNo,
                'reason' => $payment->reason,
            ], $invoice->payments()),
        ];
    }
}
