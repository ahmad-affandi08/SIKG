<?php

declare(strict_types=1);

namespace Src\Billing\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

/**
 * Tagihan satu kunjungan (agregat inti Billing). Total, jumlah dibayar, dan status DITURUNKAN di sini, tidak pernah diisi
 * dari luar (M7). Pembayaran hanya bertambah; koreksi berupa transaksi pembalik. Void tidak menghapus apa pun.
 *
 * Daur hidup: draft (item masuk dari tindakan dan obat) -> issued (diterbitkan saat kunjungan ditutup) -> void.
 */
final class Invoice
{
    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const VOID = 'void';

    /** @var list<Payment> */
    private array $newPayments = [];

    /** @var list<string> */
    private array $removedItemIds = [];

    /**
     * @param  list<InvoiceItem>  $items
     * @param  list<Payment>  $payments
     */
    private function __construct(
        public readonly string $id,
        public readonly string $patientId,
        public readonly string $encounterId,
        private string $lifecycle,
        private ?string $number,
        private ?DateTimeImmutable $issuedAt,
        private ?Discount $discount,
        private ?DateTimeImmutable $voidedAt,
        private ?string $voidReason,
        private ?string $voidedBy,
        private array $items,
        private array $payments,
    ) {}

    public static function draft(string $id, string $patientId, string $encounterId): self
    {
        return new self($id, $patientId, $encounterId, self::DRAFT, null, null, null, null, null, null, [], []);
    }

    /**
     * @param  list<InvoiceItem>  $items
     * @param  list<Payment>  $payments
     */
    public static function reconstitute(
        string $id,
        string $patientId,
        string $encounterId,
        string $lifecycle,
        ?string $number,
        ?DateTimeImmutable $issuedAt,
        ?Discount $discount,
        ?DateTimeImmutable $voidedAt,
        ?string $voidReason,
        ?string $voidedBy,
        array $items,
        array $payments,
    ): self {
        return new self($id, $patientId, $encounterId, $lifecycle, $number, $issuedAt, $discount, $voidedAt, $voidReason, $voidedBy, $items, $payments);
    }

    // --- Keadaan turunan ---

    public function lifecycle(): string
    {
        return $this->lifecycle;
    }

    public function number(): ?string
    {
        return $this->number;
    }

    public function issuedAt(): ?DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function discount(): ?Discount
    {
        return $this->discount;
    }

    public function voidedAt(): ?DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function voidReason(): ?string
    {
        return $this->voidReason;
    }

    public function voidedBy(): ?string
    {
        return $this->voidedBy;
    }

    /**
     * @return list<InvoiceItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * @return list<Payment>
     */
    public function payments(): array
    {
        return $this->payments;
    }

    /**
     * @return list<Payment>
     */
    public function newPayments(): array
    {
        return $this->newPayments;
    }

    /**
     * @return list<string>
     */
    public function removedItemIds(): array
    {
        return $this->removedItemIds;
    }

    public function item(string $itemId): ?InvoiceItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        return null;
    }

    public function subtotal(): Money
    {
        $sum = Money::zero();
        foreach ($this->items as $item) {
            $sum = $sum->add($item->total());
        }

        return $sum;
    }

    public function discountAmount(): Money
    {
        return $this->discount?->amountOn($this->subtotal()) ?? Money::zero();
    }

    public function total(): Money
    {
        return $this->subtotal()->subtract($this->discountAmount());
    }

    public function paid(): Money
    {
        $paid = 0;
        foreach ($this->payments as $payment) {
            $paid += $payment->isRefund() ? -$payment->amount->amount : $payment->amount->amount;
        }

        return Money::rupiah($paid);
    }

    /**
     * Sisa tagihan (piutang pasien, KSR-04); nol bila sudah lunas.
     */
    public function balance(): Money
    {
        $total = $this->total();
        $paid = $this->paid();

        return $paid->greaterThan($total) ? Money::zero() : $total->subtract($paid);
    }

    public function status(): InvoiceStatus
    {
        if ($this->lifecycle === self::VOID) {
            return InvoiceStatus::Void;
        }
        if ($this->lifecycle === self::DRAFT) {
            return InvoiceStatus::Draft;
        }
        $paid = $this->paid();
        if ($paid->isZero() && ! $this->total()->isZero()) {
            return InvoiceStatus::Unpaid;
        }

        return $paid->amount >= $this->total()->amount ? InvoiceStatus::Paid : InvoiceStatus::Partial;
    }

    /**
     * Item dan diskon hanya dapat diubah kasir selama belum ada uang masuk dan tagihan belum dibatalkan.
     */
    public function isEditable(): bool
    {
        return $this->lifecycle !== self::VOID && $this->paid()->isZero();
    }

    /**
     * Jumlah bersih per metode pembayaran (pembayaran dikurangi pengembalian), hanya yang positif.
     *
     * @return array<string, int>
     */
    public function netPaidByMethod(): array
    {
        $net = [];
        foreach ($this->payments as $payment) {
            $net[$payment->methodId] = ($net[$payment->methodId] ?? 0) + ($payment->isRefund() ? -$payment->amount->amount : $payment->amount->amount);
        }

        return array_filter($net, fn (int $v) => $v > 0);
    }

    // --- Item ---

    /**
     * Item dari tindakan atau obat pada kunjungan (KSR-01). Hanya selama tagihan masih draft; uang muka tidak menghalangi.
     */
    public function addClinicalItem(InvoiceItem $item): void
    {
        if ($this->lifecycle !== self::DRAFT) {
            throw new InvalidBillingData('invoice.not_editable', 'Tagihan sudah diterbitkan; item klinis tidak dapat ditambah.');
        }
        $this->guardUniqueSource($item);
        $this->items[] = $item;
    }

    public function hasSource(string $sourceType, string $sourceId): bool
    {
        foreach ($this->items as $item) {
            if ($item->sourceType === $sourceType && $item->sourceId === $sourceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Melepas item asal tindakan atau obat yang dibatalkan dari kunjungan; hanya pada draft.
     */
    public function removeSourceItem(string $sourceType, string $sourceId): bool
    {
        if ($this->lifecycle !== self::DRAFT) {
            return false;
        }
        foreach ($this->items as $index => $item) {
            if ($item->sourceType === $sourceType && $item->sourceId === $sourceId) {
                $this->removedItemIds[] = $item->id;
                array_splice($this->items, $index, 1);

                return true;
            }
        }

        return false;
    }

    public function addManualItem(InvoiceItem $item): void
    {
        $this->guardEditable();
        if (! $item->isManual()) {
            throw new InvalidBillingData('invoice.not_editable', 'Hanya item manual yang dapat ditambah kasir.');
        }
        $this->items[] = $item;
    }

    public function changeManualQuantity(string $itemId, int $quantity): void
    {
        $this->guardEditable();
        $item = $this->requireItem($itemId);
        if (! $item->isManual()) {
            throw new InvalidBillingData('invoice.not_editable', 'Jumlah item dari tindakan atau obat tidak diubah di tagihan.');
        }
        $item->changeQuantity($quantity);
    }

    public function removeManualItem(string $itemId): void
    {
        $this->guardEditable();
        $item = $this->requireItem($itemId);
        if (! $item->isManual()) {
            throw new InvalidBillingData('invoice.not_editable', 'Item dari tindakan atau obat tidak dihapus di tagihan.');
        }
        $this->removedItemIds[] = $itemId;
        $this->items = array_values(array_filter($this->items, fn (InvoiceItem $i) => $i->id !== $itemId));
    }

    // --- Diskon (KSR-02) ---

    public function applyItemDiscount(string $itemId, ?Discount $discount): void
    {
        $this->guardEditable();
        $this->requireItem($itemId)->applyDiscount($discount);
    }

    public function applyInvoiceDiscount(?Discount $discount): void
    {
        $this->guardEditable();
        $this->discount = $discount;
    }

    // --- Daur hidup ---

    public function issue(string $number, DateTimeImmutable $at): void
    {
        if ($this->lifecycle === self::VOID) {
            throw new InvalidBillingData('invoice.void', 'Tagihan sudah dibatalkan.');
        }
        if ($this->lifecycle === self::ISSUED) {
            throw new InvalidBillingData('invoice.already_issued', 'Tagihan sudah diterbitkan.');
        }
        if ($this->items === []) {
            throw new InvalidBillingData('invoice.empty', 'Tagihan tanpa item tidak dapat diterbitkan.');
        }
        if ($this->paid()->greaterThan($this->total())) {
            throw new InvalidBillingData('invoice.overpaid', 'Uang muka melebihi total tagihan; kembalikan selisihnya lebih dulu.');
        }
        $this->lifecycle = self::ISSUED;
        $this->number = $number;
        $this->issuedAt = $at;
    }

    /**
     * Mencatat pembayaran atau uang muka (KSR-03, KSR-04). Kunci idempotensi yang sama mengembalikan hasil yang sama tanpa
     * efek ganda: false berarti pengulangan (tidak ada yang berubah).
     */
    public function recordPayment(Payment $payment): bool
    {
        if ($payment->isRefund()) {
            throw new InvalidBillingData('payment.invalid_type', 'Pengembalian dicatat lewat refund.');
        }
        if ($this->isReplay($payment)) {
            return false;
        }
        if ($this->lifecycle === self::VOID) {
            throw new InvalidBillingData('invoice.void', 'Tagihan sudah dibatalkan; pembayaran tidak dapat dicatat.');
        }
        if ($payment->amount->isZero()) {
            throw new InvalidBillingData('payment.invalid_amount', 'Jumlah pembayaran harus lebih dari nol.');
        }
        if ($payment->type === PaymentType::Pay && $this->lifecycle !== self::ISSUED) {
            throw new InvalidBillingData('invoice.not_issued', 'Tagihan belum diterbitkan; catat sebagai uang muka atau terbitkan lebih dulu.');
        }
        if ($this->lifecycle === self::ISSUED) {
            if ($this->balance()->isZero()) {
                throw new InvalidBillingData('invoice.already_paid', 'Tagihan sudah lunas.');
            }
            if ($payment->amount->greaterThan($this->balance())) {
                throw new InvalidBillingData('invoice.overpayment', 'Jumlah melebihi sisa tagihan.');
            }
        }
        $this->payments[] = $payment;
        $this->newPayments[] = $payment;

        return true;
    }

    /**
     * Transaksi pembalik (retur atau refund, KSR-07): tidak menghapus pembayaran, hanya mengurangi jumlah dibayar.
     */
    public function refund(Payment $refund): bool
    {
        if (! $refund->isRefund()) {
            throw new InvalidBillingData('payment.invalid_type', 'Transaksi ini bukan pengembalian.');
        }
        if ($this->isReplay($refund)) {
            return false;
        }
        if ($refund->amount->isZero()) {
            throw new InvalidBillingData('payment.invalid_amount', 'Jumlah pengembalian harus lebih dari nol.');
        }
        if ($refund->reason === null || trim($refund->reason) === '') {
            throw new InvalidBillingData('refund.reason_required', 'Alasan pengembalian wajib diisi.');
        }
        $available = $this->netPaidByMethod()[$refund->methodId] ?? 0;
        if ($refund->amount->amount > $available) {
            throw new InvalidBillingData('refund.exceeds_paid', 'Pengembalian melebihi jumlah yang dibayar dengan metode ini.');
        }
        $this->payments[] = $refund;
        $this->newPayments[] = $refund;

        return true;
    }

    /**
     * Membatalkan tagihan (KSR-07). Uang yang sudah masuk dikembalikan lewat transaksi refund per metode; tidak ada yang
     * dihapus. Hanya wewenang Admin atau Pemilik; dicek di Presentation lewat izin invoice.void.
     *
     * @return list<Payment> refund yang dibuat
     */
    public function void(string $by, string $reason, DateTimeImmutable $at, IdGenerator $ids): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidBillingData('invoice.void_reason_required', 'Alasan pembatalan wajib diisi.');
        }
        if ($this->lifecycle === self::VOID) {
            throw new InvalidBillingData('invoice.already_void', 'Tagihan sudah dibatalkan.');
        }

        $refunds = [];
        foreach ($this->netPaidByMethod() as $methodId => $amount) {
            $refund = new Payment($ids->generate(), $methodId, PaymentType::Refund, Money::rupiah($amount), $at, $by, null, 'void-'.$methodId, null, 'Tagihan dibatalkan: '.$reason);
            $this->payments[] = $refund;
            $this->newPayments[] = $refund;
            $refunds[] = $refund;
        }

        $this->lifecycle = self::VOID;
        $this->voidedAt = $at;
        $this->voidReason = mb_substr($reason, 0, 255);
        $this->voidedBy = $by;

        return $refunds;
    }

    // --- Pembantu ---

    private function guardEditable(): void
    {
        if ($this->lifecycle === self::VOID) {
            throw new InvalidBillingData('invoice.void', 'Tagihan sudah dibatalkan.');
        }
        if (! $this->paid()->isZero()) {
            throw new InvalidBillingData('invoice.not_editable', 'Tagihan sudah memiliki pembayaran; item dan diskon tidak dapat diubah.');
        }
    }

    private function requireItem(string $itemId): InvoiceItem
    {
        return $this->item($itemId) ?? throw new InvalidBillingData('invoice.item_not_found', 'Item tagihan tidak ditemukan.');
    }

    private function guardUniqueSource(InvoiceItem $item): void
    {
        if ($item->sourceId !== null && $this->hasSource($item->sourceType, $item->sourceId)) {
            throw new InvalidBillingData('invoice.duplicate_source', 'Item dari sumber ini sudah ada di tagihan.');
        }
    }

    private function isReplay(Payment $payment): bool
    {
        foreach ($this->payments as $existing) {
            if ($existing->idempotencyKey === $payment->idempotencyKey) {
                if ($existing->type !== $payment->type || $existing->methodId !== $payment->methodId || ! $existing->amount->equals($payment->amount)) {
                    throw new InvalidBillingData('payment.idempotency_conflict', 'Idempotency-Key sudah dipakai untuk pembayaran yang berbeda.');
                }

                return true;
            }
        }

        return false;
    }
}
