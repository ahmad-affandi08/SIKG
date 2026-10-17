<?php

declare(strict_types=1);

namespace Src\Billing\Application;

use Src\Billing\Domain\Discount;
use Src\Billing\Domain\Events\InvoiceIssued;
use Src\Billing\Domain\Events\InvoiceVoided;
use Src\Billing\Domain\Events\PaymentReceived;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceItem;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Payment;
use Src\Billing\Domain\PaymentType;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

/**
 * KSR-01 sampai KSR-04 dan KSR-07: tindakan kasir dan admin pada tagihan. Satu use case, satu transaksi, tagihan dikunci
 * (FOR UPDATE) selama perubahan. Semua perubahan tercatat di audit (M10). Izin dicek di Presentation (M9).
 */
final readonly class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private BillingNumbering $numbering,
        private PaymentMethodCatalog $methods,
        private Transaction $tx,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private IdGenerator $ids,
        private Clock $clock,
    ) {}

    public function addManualItem(string $actorId, string $invoiceId, string $description, int $quantity, int $unitPriceAmount): string
    {
        return $this->mutate($actorId, $invoiceId, 'invoice.item_added', function (Invoice $invoice) use ($description, $quantity, $unitPriceAmount): array {
            $item = InvoiceItem::create($this->ids->generate(), InvoiceItem::SOURCE_MANUAL, null, $description, $quantity, Money::rupiah($unitPriceAmount));
            $invoice->addManualItem($item);

            return [$item->id, ['item_id' => $item->id, 'description' => $item->description, 'quantity' => $quantity, 'unit_price' => $unitPriceAmount]];
        });
    }

    public function changeQuantity(string $actorId, string $invoiceId, string $itemId, int $quantity): void
    {
        $this->mutate($actorId, $invoiceId, 'invoice.item_quantity_changed', function (Invoice $invoice) use ($itemId, $quantity): array {
            $before = $invoice->item($itemId)?->quantity();
            $invoice->changeManualQuantity($itemId, $quantity);

            return [null, ['item_id' => $itemId, 'quantity_before' => $before, 'quantity' => $quantity]];
        });
    }

    public function removeItem(string $actorId, string $invoiceId, string $itemId): void
    {
        $this->mutate($actorId, $invoiceId, 'invoice.item_removed', function (Invoice $invoice) use ($itemId): array {
            $invoice->removeManualItem($itemId);

            return [null, ['item_id' => $itemId]];
        });
    }

    /**
     * Menerbitkan draft secara manual (mis. kasir menagih sebelum kunjungan ditutup). Biasanya terbit otomatis saat kunjungan ditutup.
     */
    public function issue(string $actorId, string $invoiceId): void
    {
        /** @var InvoiceIssued|null $issued */
        $issued = null;
        $this->mutate($actorId, $invoiceId, 'invoice.issued', function (Invoice $invoice) use (&$issued): array {
            $now = $this->clock->now();
            $invoice->issue($this->numbering->invoiceNumber(), $now);
            $issued = new InvoiceIssued($invoice->id, $invoice->encounterId, $invoice->total()->amount, $now);

            return [null, ['number' => $invoice->number(), 'total' => $invoice->total()->amount, 'trigger' => 'manual']];
        });
        if ($issued !== null) {
            $this->events->dispatch($issued);
        }
    }

    /**
     * @param  int  $limitBasisPoints  batas diskon peran pengguna (KSR-02); diskon di atasnya ditolak
     */
    public function applyItemDiscount(string $actorId, string $invoiceId, string $itemId, ?Discount $discount, int $limitBasisPoints): void
    {
        $this->mutate($actorId, $invoiceId, 'invoice.item_discount_applied', function (Invoice $invoice) use ($itemId, $discount, $limitBasisPoints): array {
            $item = $invoice->item($itemId) ?? throw new InvalidBillingData('invoice.item_not_found', 'Item tagihan tidak ditemukan.');
            $this->guardLimit($discount, $item->gross(), $limitBasisPoints);
            $before = $item->discountAmount()->amount;
            $invoice->applyItemDiscount($itemId, $discount);

            return [null, ['item_id' => $itemId, 'discount_before' => $before, 'discount' => $item->discountAmount()->amount, 'reason' => $discount?->reason]];
        });
    }

    public function applyInvoiceDiscount(string $actorId, string $invoiceId, ?Discount $discount, int $limitBasisPoints): void
    {
        $this->mutate($actorId, $invoiceId, 'invoice.discount_applied', function (Invoice $invoice) use ($discount, $limitBasisPoints): array {
            $this->guardLimit($discount, $invoice->subtotal(), $limitBasisPoints);
            $before = $invoice->discountAmount()->amount;
            $invoice->applyInvoiceDiscount($discount);

            return [null, ['discount_before' => $before, 'discount' => $invoice->discountAmount()->amount, 'reason' => $discount?->reason]];
        });
    }

    /**
     * Mencatat pembayaran atau uang muka (KSR-03, KSR-04). Idempoten terhadap kunci: pengulangan mengembalikan pembayaran
     * yang sama tanpa efek ganda (M7).
     */
    public function recordPayment(string $actorId, string $invoiceId, string $methodId, PaymentType $type, int $amount, string $idempotencyKey, ?string $referenceNo = null): Payment
    {
        $result = null;
        $events = [];
        $this->mutate($actorId, $invoiceId, 'payment.recorded', function (Invoice $invoice) use ($actorId, $methodId, $type, $amount, $idempotencyKey, $referenceNo, &$result, &$events): array {
            $this->requireActiveMethod($methodId);
            $existing = null;
            foreach ($invoice->payments() as $candidateExisting) {
                if ($candidateExisting->idempotencyKey === $idempotencyKey) {
                    $existing = $candidateExisting;
                }
            }
            $candidate = new Payment(
                $existing === null ? $this->ids->generate() : $existing->id,
                $methodId,
                $type,
                Money::rupiah($amount),
                $this->clock->now(),
                $actorId,
                self::clean($referenceNo, 100),
                $idempotencyKey,
                $existing === null ? $this->numbering->receiptNumber() : $existing->receiptNo,
                null,
            );
            $recorded = $invoice->recordPayment($candidate);
            $result = $recorded ? $candidate : ($existing ?? $candidate);
            if ($recorded) {
                $events[] = new PaymentReceived($invoice->id, $candidate->id, $amount, $candidate->paidAt);
            }

            return [null, $recorded ? ['payment_id' => $candidate->id, 'type' => $type->value, 'amount' => $amount, 'method_id' => $methodId, 'receipt_no' => $candidate->receiptNo, 'paid_after' => $invoice->paid()->amount] : null];
        });
        $this->events->dispatch(...$events);

        return $result ?? throw new InvalidBillingData('payment.not_recorded', 'Pembayaran tidak tercatat.');
    }

    /**
     * Retur atau refund sebagian (KSR-07): transaksi pembalik, pembayaran asal tidak dihapus.
     */
    public function refund(string $actorId, string $invoiceId, string $methodId, int $amount, string $reason, string $idempotencyKey): void
    {
        $this->mutate($actorId, $invoiceId, 'payment.refunded', function (Invoice $invoice) use ($actorId, $methodId, $amount, $reason, $idempotencyKey): array {
            $this->requireActiveMethod($methodId);
            $refund = new Payment($this->ids->generate(), $methodId, PaymentType::Refund, Money::rupiah($amount), $this->clock->now(), $actorId, null, $idempotencyKey, null, self::clean($reason, 255));
            $recorded = $invoice->refund($refund);

            return [null, $recorded ? ['payment_id' => $refund->id, 'amount' => $amount, 'method_id' => $methodId, 'reason' => $refund->reason, 'paid_after' => $invoice->paid()->amount] : null];
        });
    }

    /**
     * Pembatalan (KSR-07): hanya Admin atau Pemilik (izin invoice.void di Presentation). Uang yang sudah masuk dikembalikan
     * lewat refund per metode.
     */
    public function void(string $actorId, string $invoiceId, string $reason): void
    {
        $voided = null;
        $this->mutate($actorId, $invoiceId, 'invoice.voided', function (Invoice $invoice) use ($actorId, $reason, &$voided): array {
            $now = $this->clock->now();
            $paidBefore = $invoice->paid()->amount;
            $refunds = $invoice->void($actorId, $reason, $now, $this->ids);
            $voided = new InvoiceVoided($invoice->id, $now);

            return [null, ['reason' => $invoice->voidReason(), 'paid_refunded' => $paidBefore, 'refunds' => count($refunds)]];
        });
        if ($voided !== null) {
            $this->events->dispatch($voided);
        }
    }

    /**
     * @template T
     *
     * @param  non-empty-string  $action
     * @param  callable(Invoice): array{0: T, 1: array<string, mixed>|null}  $change  mengembalikan [hasil, data audit]
     * @return T
     */
    private function mutate(string $actorId, string $invoiceId, string $action, callable $change): mixed
    {
        return $this->tx->run(function () use ($actorId, $invoiceId, $action, $change): mixed {
            $invoice = $this->invoices->lockById($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
            $beforeStatus = $invoice->status()->value;
            [$result, $data] = $change($invoice);
            if ($data !== null) {
                $this->invoices->save($invoice, $actorId);
                $this->audit->record(new AuditEntry($actorId, $action, 'invoice', $invoice->id, ['status' => $beforeStatus], [
                    ...$data,
                    'status' => $invoice->status()->value,
                    'total' => $invoice->total()->amount,
                ]));
            }

            return $result;
        });
    }

    private function guardLimit(?Discount $discount, Money $base, int $limitBasisPoints): void
    {
        if ($discount !== null && $discount->basisPointsOn($base) > $limitBasisPoints) {
            throw new InvalidBillingData('discount.exceeds_limit', 'Diskon melebihi batas untuk peran Anda ('.self::percentText($limitBasisPoints).'). Minta Admin atau Pemilik.');
        }
    }

    private function requireActiveMethod(string $methodId): void
    {
        $method = $this->methods->find($methodId);
        if ($method === null || ! $method->active) {
            throw new InvalidBillingData('payment.method_not_found', 'Metode pembayaran tidak ditemukan atau nonaktif.');
        }
    }

    private static function percentText(int $basisPoints): string
    {
        $whole = intdiv($basisPoints, 100);
        $frac = $basisPoints % 100;

        return ($frac === 0 ? (string) $whole : $whole.','.rtrim(str_pad((string) $frac, 2, '0', STR_PAD_LEFT), '0')).'%';
    }

    private static function clean(?string $value, int $max): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : mb_substr($value, 0, $max);
    }
}
