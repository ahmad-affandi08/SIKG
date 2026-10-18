<?php

declare(strict_types=1);

namespace Src\Billing\Application\Loyalty;

use DateTimeZone;
use Src\Billing\Domain\Discount;
use Src\Billing\Domain\DiscountKind;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\Invoice;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Loyalty\LoyaltyRepository;
use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PromotionRecord;
use Src\Billing\Domain\Loyalty\PromotionRepository;
use Src\Billing\Domain\Loyalty\Voucher;
use Src\Billing\Domain\Loyalty\VoucherRepository;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P3-06: promosi pada tagihan. Voucher dan penukaran poin memakai slot diskon tagihan (satu promosi aktif per tagihan, tidak
 * bersama diskon manual); sesi paket kunjungan memakai diskon item. Semua lewat tagihan yang dikunci dan hanya selama tagihan
 * masih dapat diubah (belum ada uang masuk, KSR-02). Diskon dari promosi sudah disahkan di master sehingga tidak diperiksa terhadap
 * batas diskon peran. Setiap langkah tercatat di audit (M10). Izin dicek di Presentation (M9).
 */
final readonly class PromotionService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private VoucherRepository $vouchers,
        private PromotionRepository $promotions,
        private LoyaltyRepository $loyalty,
        private PackageRepository $packages,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    public function applyVoucher(string $actorId, string $invoiceId, string $code): void
    {
        $code = Voucher::normalize($code);
        if ($code === '') {
            throw new InvalidBillingData('voucher.code_required', 'Kode promo wajib diisi.');
        }
        $this->tx->run(function () use ($actorId, $invoiceId, $code): void {
            $invoice = $this->lock($invoiceId);
            $this->assertSlotFree($invoice);
            $voucher = $this->vouchers->lockByCode($code) ?? throw new InvalidBillingData('voucher.not_found', 'Kode promo tidak ditemukan.');
            $voucher->assertUsable($invoice->subtotal(), $this->vouchers->appliedTotal($voucher->id), $this->vouchers->appliedByPatient($voucher->id, $invoice->patientId), $this->today());

            $invoice->applyInvoiceDiscount($voucher->discount());
            $amount = $invoice->discountAmount()->amount;
            $this->invoices->save($invoice, $actorId);
            $this->promotions->add(new PromotionRecord($this->ids->generate(), $invoice->id, $invoice->patientId, PromotionRecord::VOUCHER, $voucher->id, 0, $amount), $actorId);
            $this->audit->record(new AuditEntry($actorId, 'promo.voucher_applied', 'invoice', $invoice->id, null, ['voucher' => $voucher->code, 'discount' => $amount, 'total' => $invoice->total()->amount]));
        });
    }

    public function redeemPoints(string $actorId, string $invoiceId, int $points): void
    {
        $this->tx->run(function () use ($actorId, $invoiceId, $points): void {
            $invoice = $this->lock($invoiceId);
            $this->assertSlotFree($invoice);
            $settings = $this->loyalty->settings();
            if (! $settings->enabled) {
                throw new InvalidBillingData('points.disabled', 'Poin loyalitas belum diaktifkan di klinik ini.');
            }
            if ($points < $settings->minRedeemPoints) {
                throw new InvalidBillingData('points.below_minimum', 'Penukaran minimal '.$settings->minRedeemPoints.' poin.');
            }
            $max = $settings->maxPointsFor($invoice->subtotal()->amount);
            if ($points > $max) {
                throw new InvalidBillingData('points.exceeds_subtotal', 'Poin yang ditukar melebihi nilai tagihan; maksimal '.$max.' poin.');
            }
            if ($points > $this->loyalty->lockedBalance($invoice->patientId)) {
                throw new InvalidBillingData('points.insufficient', 'Saldo poin pasien tidak cukup.');
            }

            $value = $points * $settings->pointValueAmount;
            $invoice->applyInvoiceDiscount(new Discount(DiscountKind::Amount, $value, 'Tukar '.$points.' poin'));
            $this->invoices->save($invoice, $actorId);
            $promotion = new PromotionRecord($this->ids->generate(), $invoice->id, $invoice->patientId, PromotionRecord::POINTS, null, $points, $invoice->discountAmount()->amount);
            $this->promotions->add($promotion, $actorId);
            $this->loyalty->append($invoice->patientId, -$points, 'redeem', $invoice->id, 'redeem:'.$promotion->id, 'Ditukar pada tagihan', $actorId);
            $this->audit->record(new AuditEntry($actorId, 'promo.points_redeemed', 'invoice', $invoice->id, null, ['points' => $points, 'discount' => $promotion->discountAmount, 'total' => $invoice->total()->amount]));
        });
    }

    /**
     * Melepas voucher atau penukaran poin: diskon dicabut, pemakaian voucher dan poin dikembalikan.
     */
    public function remove(string $actorId, string $invoiceId): void
    {
        $this->tx->run(function () use ($actorId, $invoiceId): void {
            $invoice = $this->lock($invoiceId);
            $promotion = $this->promotions->release($invoice->id, 'Dilepas oleh petugas') ?? throw new InvalidBillingData('promo.none', 'Tagihan ini tidak memakai promo.');
            $invoice->applyInvoiceDiscount(null);
            $this->invoices->save($invoice, $actorId);
            if ($promotion->pointsUsed > 0) {
                $this->loyalty->append($invoice->patientId, $promotion->pointsUsed, 'release', $invoice->id, 'release:'.$promotion->id, 'Penukaran dilepas', $actorId);
            }
            $this->audit->record(new AuditEntry($actorId, 'promo.removed', 'invoice', $invoice->id, ['type' => $promotion->type, 'discount' => $promotion->discountAmount], ['total' => $invoice->total()->amount]));
        });
    }

    public function applyPackage(string $actorId, string $invoiceId, string $itemId, string $patientPackageId): void
    {
        $this->tx->run(function () use ($actorId, $invoiceId, $itemId, $patientPackageId): void {
            $invoice = $this->lock($invoiceId);
            $package = $this->packages->lock($patientPackageId);
            if ($package === null || $package->patientId !== $invoice->patientId) {
                throw new InvalidBillingData('package.not_found', 'Paket pasien tidak ditemukan.');
            }
            $package->assertUsable($this->today());
            $item = $invoice->item($itemId) ?? throw new InvalidBillingData('invoice.item_not_found', 'Item tagihan tidak ditemukan.');
            if ($item->discount() !== null || $this->packages->itemHasUsage($itemId)) {
                throw new InvalidBillingData('package.item_discounted', 'Item ini sudah memiliki diskon atau sesi paket.');
            }
            $coverage = $package->coverageFor($item->unitPrice->amount);
            if ($coverage < 1) {
                throw new InvalidBillingData('package.no_coverage', 'Item ini tidak memiliki nilai yang dapat ditanggung paket.');
            }

            $invoice->applyItemDiscount($itemId, new Discount(DiscountKind::Amount, $coverage, 'Paket '.$package->name));
            $this->invoices->save($invoice, $actorId);
            $this->packages->changeSessions($patientPackageId, -1);
            $this->packages->addUsage($patientPackageId, $invoice->id, $itemId, $item->discountAmount()->amount, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'promo.package_used', 'invoice', $invoice->id, null, ['package' => $package->name, 'item_id' => $itemId, 'covered' => $item->discountAmount()->amount, 'sessions_left' => $package->sessionsLeft - 1]));
        });
    }

    public function removePackage(string $actorId, string $invoiceId, string $itemId): void
    {
        $this->tx->run(function () use ($actorId, $invoiceId, $itemId): void {
            $invoice = $this->lock($invoiceId);
            $usage = null;
            foreach ($this->packages->appliedUsages($invoice->id) as $candidate) {
                if ($candidate['item_id'] === $itemId) {
                    $usage = $candidate;
                }
            }
            if ($usage === null) {
                throw new InvalidBillingData('package.none', 'Item ini tidak memakai sesi paket.');
            }
            $invoice->applyItemDiscount($itemId, null);
            $this->invoices->save($invoice, $actorId);
            $this->packages->releaseUsage($usage['id']);
            $this->packages->changeSessions($usage['patient_package_id'], 1);
            $this->audit->record(new AuditEntry($actorId, 'promo.package_released', 'invoice', $invoice->id, ['item_id' => $itemId, 'covered' => $usage['value_amount']], null));
        });
    }

    private function lock(string $invoiceId): Invoice
    {
        return $this->invoices->lockById($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
    }

    private function assertSlotFree(Invoice $invoice): void
    {
        if ($this->promotions->activeForInvoice($invoice->id) !== null) {
            throw new InvalidBillingData('promo.already_applied', 'Tagihan ini sudah memakai promo; lepas dulu untuk menggantinya.');
        }
        if ($invoice->discount() !== null) {
            throw new InvalidBillingData('promo.discount_exists', 'Tagihan sudah memiliki diskon manual; hapus diskon itu dulu.');
        }
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
