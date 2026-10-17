<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure;

use DateTimeImmutable;
use Src\Billing\Domain\Discount;
use Src\Billing\Domain\DiscountKind;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceItem;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Payment;
use Src\Billing\Domain\PaymentType;
use Src\Billing\Infrastructure\Eloquent\InvoiceItemModel;
use Src\Billing\Infrastructure\Eloquent\InvoiceModel;
use Src\Billing\Infrastructure\Eloquent\PaymentModel;
use Src\Shared\Domain\Money;

/**
 * Memetakan agregat Invoice ke tabel invoices, invoice_items, dan payments. Kolom total, dibayar, dan status disimpan
 * sebagai hasil turunan domain untuk daftar dan laporan; sumber kebenarannya tetap item dan pembayaran.
 */
final class EloquentInvoiceRepository implements InvoiceRepository
{
    public function find(string $id): ?Invoice
    {
        $row = InvoiceModel::query()->find($id);

        return $row === null ? null : $this->hydrate($row);
    }

    public function lockById(string $id): ?Invoice
    {
        $row = InvoiceModel::query()->lockForUpdate()->find($id);

        return $row === null ? null : $this->hydrate($row);
    }

    public function lockByEncounter(string $encounterId): ?Invoice
    {
        $row = InvoiceModel::query()->lockForUpdate()->where('encounter_id', $encounterId)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function save(Invoice $invoice, ?string $actorId): void
    {
        $row = InvoiceModel::query()->find($invoice->id);
        $isNew = $row === null;
        $row ??= new InvoiceModel;
        $discount = $invoice->discount();

        $row->forceFill([
            'id' => $invoice->id,
            'number' => $invoice->number(),
            'patient_id' => $invoice->patientId,
            'encounter_id' => $invoice->encounterId,
            'lifecycle' => $invoice->lifecycle(),
            'status' => $invoice->status()->value,
            'subtotal_amount' => $invoice->subtotal()->amount,
            'discount_amount' => $invoice->discountAmount()->amount,
            'tax_basis_points' => $invoice->taxBasisPoints,
            'tax_amount' => $invoice->taxAmount()->amount,
            'total_amount' => $invoice->total()->amount,
            'paid_amount' => $invoice->paid()->amount,
            'discount_type' => $discount?->kind->value,
            'discount_value' => $discount?->value,
            'discount_reason' => $discount?->reason,
            'issued_at' => $invoice->issuedAt(),
            'voided_at' => $invoice->voidedAt(),
            'void_reason' => $invoice->voidReason(),
            'voided_by' => $invoice->voidedBy(),
            'updated_by' => $actorId,
        ]);
        if ($isNew) {
            $row->forceFill(['created_by' => $actorId]);
        }
        $row->save();

        $existing = InvoiceItemModel::query()->where('invoice_id', $invoice->id)->get()->keyBy('id');
        foreach ($invoice->items() as $item) {
            $itemRow = $existing->get($item->id) ?? new InvoiceItemModel;
            $itemDiscount = $item->discount();
            $itemRow->forceFill([
                'id' => $item->id,
                'invoice_id' => $invoice->id,
                'source_type' => $item->sourceType,
                'source_id' => $item->sourceId,
                'description' => $item->description,
                'quantity' => $item->quantity(),
                'unit_price_amount' => $item->unitPrice->amount,
                'discount_type' => $itemDiscount?->kind->value,
                'discount_value' => $itemDiscount?->value,
                'discount_reason' => $itemDiscount?->reason,
                'discount_amount' => $item->discountAmount()->amount,
                'total_amount' => $item->total()->amount,
                'updated_by' => $actorId,
            ]);
            if (! $itemRow->exists) {
                $itemRow->forceFill(['created_by' => $actorId]);
            }
            $itemRow->save();
        }
        if ($invoice->removedItemIds() !== []) {
            InvoiceItemModel::query()->whereIn('id', $invoice->removedItemIds())->where('invoice_id', $invoice->id)->get()->each->delete();
        }

        foreach ($invoice->newPayments() as $payment) {
            $paymentRow = new PaymentModel;
            $paymentRow->forceFill([
                'id' => $payment->id,
                'invoice_id' => $invoice->id,
                'payment_method_id' => $payment->methodId,
                'type' => $payment->type->value,
                'amount' => $payment->amount->amount,
                'paid_at' => $payment->paidAt,
                'received_by' => $payment->receivedBy,
                'reference_no' => $payment->referenceNo,
                'idempotency_key' => $payment->idempotencyKey,
                'receipt_no' => $payment->receiptNo,
                'reason' => $payment->reason,
            ])->save();
        }
    }

    private function hydrate(InvoiceModel $row): Invoice
    {
        $items = InvoiceItemModel::query()->where('invoice_id', $row->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (InvoiceItemModel $i) => InvoiceItem::reconstitute(
                $i->id,
                $i->source_type,
                $i->source_id,
                $i->description,
                $i->quantity,
                Money::rupiah($i->unit_price_amount),
                $this->discount($i->discount_type, $i->discount_value, $i->discount_reason),
            ))->all();
        $payments = PaymentModel::query()->where('invoice_id', $row->id)->orderBy('paid_at')->orderBy('id')->get()
            ->map(fn (PaymentModel $p) => new Payment(
                $p->id,
                $p->payment_method_id,
                PaymentType::from($p->type),
                Money::rupiah($p->amount),
                DateTimeImmutable::createFromInterface($p->paid_at),
                $p->received_by,
                $p->reference_no,
                $p->idempotency_key,
                $p->receipt_no,
                $p->reason,
            ))->all();

        return Invoice::reconstitute(
            $row->id,
            $row->patient_id,
            $row->encounter_id,
            $row->lifecycle,
            $row->number,
            $row->issued_at === null ? null : DateTimeImmutable::createFromInterface($row->issued_at),
            $this->discount($row->discount_type, $row->discount_value, $row->discount_reason),
            $row->voided_at === null ? null : DateTimeImmutable::createFromInterface($row->voided_at),
            $row->void_reason,
            $row->voided_by,
            array_values($items),
            array_values($payments),
            $row->tax_basis_points,
        );
    }

    private function discount(?string $type, ?int $value, ?string $reason): ?Discount
    {
        if ($type === null || $value === null || $reason === null) {
            return null;
        }

        return new Discount(DiscountKind::from($type), $value, $reason);
    }
}
