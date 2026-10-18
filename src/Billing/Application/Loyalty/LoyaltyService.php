<?php

declare(strict_types=1);

namespace Src\Billing\Application\Loyalty;

use Src\Billing\Domain\Events\InvoiceVoided;
use Src\Billing\Domain\Events\PaymentReceived;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\InvoiceStatus;
use Src\Billing\Domain\Loyalty\LoyaltyRepository;
use Src\Billing\Domain\Loyalty\LoyaltySettings;
use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PromotionRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * P3-06: poin loyalitas. Poin didapat sekali saat tagihan lunas (kunci dedupe per tagihan) sebesar kelipatan belanja menurut
 * pengaturan klinik. Tagihan yang dibatalkan melepas promosi, mengembalikan poin yang ditukar, membalik poin yang didapat, melepas
 * sesi paket, dan membatalkan paket yang dibeli lewat tagihan itu. Buku poin hanya bertambah (M5). Pendengar event Billing.
 */
final readonly class LoyaltyService
{
    public function __construct(
        private LoyaltyRepository $loyalty,
        private InvoiceRepository $invoices,
        private PromotionRepository $promotions,
        private PackageRepository $packages,
        private Transaction $tx,
        private AuditLogger $audit,
    ) {}

    public function saveSettings(string $actorId, bool $enabled, int $rupiahPerPoint, int $pointValueAmount, int $minRedeemPoints): void
    {
        if ($rupiahPerPoint < 1 || $rupiahPerPoint > 100_000_000 || $pointValueAmount < 1 || $pointValueAmount > 100_000_000 || $minRedeemPoints < 1 || $minRedeemPoints > 100_000) {
            throw new InvalidBillingData('points.settings_invalid', 'Nilai pengaturan poin harus bilangan bulat positif dalam batas wajar.');
        }
        $this->tx->run(function () use ($actorId, $enabled, $rupiahPerPoint, $pointValueAmount, $minRedeemPoints): void {
            $before = $this->loyalty->settings();
            $after = new LoyaltySettings($enabled, $rupiahPerPoint, $pointValueAmount, $minRedeemPoints);
            $this->loyalty->saveSettings($actorId, $after);
            $this->audit->record(new AuditEntry($actorId, 'loyalty.settings_changed', 'clinic', 'loyalty', [
                'enabled' => $before->enabled, 'rupiah_per_point' => $before->rupiahPerPoint, 'point_value' => $before->pointValueAmount, 'min_redeem' => $before->minRedeemPoints,
            ], ['enabled' => $enabled, 'rupiah_per_point' => $rupiahPerPoint, 'point_value' => $pointValueAmount, 'min_redeem' => $minRedeemPoints]));
        });
    }

    /**
     * Pendengar PaymentReceived: tagihan yang baru lunas memberi poin sekali.
     */
    public function paymentReceived(PaymentReceived $event): void
    {
        $this->tx->run(function () use ($event): void {
            $settings = $this->loyalty->settings();
            if (! $settings->enabled) {
                return;
            }
            $invoice = $this->invoices->find($event->invoiceId);
            if ($invoice === null || $invoice->status() !== InvoiceStatus::Paid) {
                return;
            }
            $points = $settings->pointsFor($invoice->total()->amount);
            if ($points < 1) {
                return;
            }
            if ($this->loyalty->append($invoice->patientId, $points, 'earn', $invoice->id, 'earn:'.$invoice->id, 'Tagihan lunas', null)) {
                $this->audit->record(new AuditEntry(null, 'loyalty.points_earned', 'invoice', $invoice->id, null, ['points' => $points, 'total' => $invoice->total()->amount]));
            }
        });
    }

    /**
     * Pendengar InvoiceVoided: semua efek promosi dan loyalitas tagihan itu dibalik.
     */
    public function invoiceVoided(InvoiceVoided $event): void
    {
        $this->tx->run(function () use ($event): void {
            $invoice = $this->invoices->find($event->invoiceId);
            if ($invoice === null) {
                return;
            }
            $promotion = $this->promotions->release($invoice->id, 'Tagihan dibatalkan');
            if ($promotion !== null && $promotion->pointsUsed > 0) {
                $this->loyalty->append($invoice->patientId, $promotion->pointsUsed, 'release', $invoice->id, 'release:'.$promotion->id, 'Tagihan dibatalkan', null);
            }
            $earned = $this->loyalty->earnedFor($invoice->id);
            if ($earned > 0) {
                $reverse = min($earned, max(0, $this->loyalty->lockedBalance($invoice->patientId)));
                if ($reverse > 0) {
                    $this->loyalty->append($invoice->patientId, -$reverse, 'reverse', $invoice->id, 'reverse:'.$invoice->id, 'Tagihan dibatalkan', null);
                }
            }
            $released = 0;
            foreach ($this->packages->appliedUsages($invoice->id) as $usage) {
                $this->packages->releaseUsage($usage['id']);
                $this->packages->changeSessions($usage['patient_package_id'], 1);
                $released++;
            }
            $voided = $this->packages->voidPurchasedBy($invoice->id);
            if ($promotion !== null || $released > 0 || $voided > 0 || $earned > 0) {
                $this->audit->record(new AuditEntry(null, 'promo.reversed_on_void', 'invoice', $invoice->id, null, [
                    'promotion' => $promotion?->type, 'points_returned' => $promotion === null ? 0 : $promotion->pointsUsed, 'points_earned_before' => $earned, 'package_sessions_released' => $released, 'packages_voided' => $voided,
                ]));
            }
        });
    }
}
