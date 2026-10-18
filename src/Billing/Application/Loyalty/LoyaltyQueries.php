<?php

declare(strict_types=1);

namespace Src\Billing\Application\Loyalty;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Domain\InvalidBillingData;
use Src\Billing\Domain\InvoiceRepository;
use Src\Billing\Domain\Loyalty\LoyaltyRepository;
use Src\Billing\Domain\Loyalty\PackageRepository;
use Src\Billing\Domain\Loyalty\PatientPackage;
use Src\Billing\Domain\Loyalty\PromotionRecord;
use Src\Billing\Domain\Loyalty\PromotionRepository;
use Src\Billing\Domain\Loyalty\VoucherRepository;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\Clock;

/**
 * P3-06: data layar promo pada tagihan dan ringkasan loyalitas pasien (saldo poin, buku poin, paket). Hanya baca; membuka data
 * pasien tercatat di access log (M10). Izin dicek di Presentation.
 */
final readonly class LoyaltyQueries
{
    public function __construct(
        private InvoiceRepository $invoices,
        private PromotionRepository $promotions,
        private VoucherRepository $vouchers,
        private LoyaltyRepository $loyalty,
        private PackageRepository $packages,
        private PatientLookup $patients,
        private EncounterLookup $encounters,
        private AccessLogger $access,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function invoicePanel(string $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId) ?? throw new InvalidBillingData('invoice.not_found', 'Tagihan tidak ditemukan.');
        $this->access->patientRecordOpened($invoice->patientId, 'invoices.promotions');
        $patient = $this->patients->summaries([$invoice->patientId])[$invoice->patientId] ?? null;
        $settings = $this->loyalty->settings();
        $promotion = $this->promotions->activeForInvoice($invoice->id);
        $usages = [];
        foreach ($this->packages->appliedUsages($invoice->id) as $u) {
            $usages[$u['item_id']] = $u['value_amount'];
        }
        $today = $this->today();

        $voucher = $promotion?->voucherId === null ? null : $this->vouchers->find($promotion->voucherId);

        return [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number(),
                'patient_id' => $invoice->patientId,
                'patient_name' => $patient->name ?? '-',
                'medical_record_no' => $patient->medicalRecordNo ?? '-',
                'subtotal_amount' => $invoice->subtotal()->amount,
                'total_amount' => $invoice->total()->amount,
                'editable' => $invoice->isEditable(),
                'has_manual_discount' => $invoice->discount() !== null && $promotion === null,
            ],
            'promotion' => $promotion === null ? null : [
                'type' => $promotion->type,
                'label' => $promotion->type === PromotionRecord::VOUCHER ? 'Voucher '.($voucher->code ?? '') : 'Tukar '.$promotion->pointsUsed.' poin',
                'discount_amount' => $promotion->discountAmount,
            ],
            'loyalty' => [
                'enabled' => $settings->enabled,
                'balance' => $this->loyalty->balance($invoice->patientId),
                'point_value_amount' => $settings->pointValueAmount,
                'min_redeem_points' => $settings->minRedeemPoints,
                'max_points' => $settings->maxPointsFor($invoice->subtotal()->amount),
            ],
            'items' => array_map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'unit_price_amount' => $item->unitPrice->amount,
                'discount_amount' => $item->discountAmount()->amount,
                'package_covered' => $usages[$item->id] ?? null,
            ], $invoice->items()),
            'packages' => array_map(fn (PatientPackage $p) => ['id' => $p->id, 'name' => $p->name, 'sessions_left' => $p->sessionsLeft, 'expires_on' => $p->expiresOn], array_values(array_filter(
                $this->packages->forPatient($invoice->patientId),
                fn (PatientPackage $p) => $p->status === 'active' && $p->sessionsLeft > 0 && ($p->expiresOn === null || $p->expiresOn >= $today),
            ))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function patientOverview(string $patientId): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new InvalidBillingData('patient.not_found', 'Pasien tidak ditemukan.');
        $this->access->patientRecordOpened($patientId, 'patients.loyalty');
        $settings = $this->loyalty->settings();
        $zone = new DateTimeZone($this->calendar->timezone());

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'loyalty' => [
                'enabled' => $settings->enabled,
                'balance' => $this->loyalty->balance($patientId),
                'point_value_amount' => $settings->pointValueAmount,
                'ledger' => array_map(fn (array $l) => [...$l, 'created_at' => (new DateTimeImmutable($l['created_at']))->setTimezone($zone)->format('Y-m-d H:i')], $this->loyalty->ledger($patientId, 30)),
            ],
            'packages' => array_map(fn (PatientPackage $p) => [
                'id' => $p->id, 'name' => $p->name, 'sessions_total' => $p->sessionsTotal, 'sessions_left' => $p->sessionsLeft, 'expires_on' => $p->expiresOn, 'status' => $p->status,
            ], $this->packages->forPatient($patientId)),
            'catalog' => array_map(fn (array $c) => ['value' => $c['id'], 'label' => $c['name'].' ('.$c['sessions'].' sesi)', 'price_amount' => $c['price_amount']], $this->packages->activeCatalog()),
            'open_encounters' => array_map(fn ($e) => ['value' => $e->id, 'label' => 'Kunjungan berjalan '.substr($e->id, -6)], $this->encounters->openForPatient($patientId)),
        ];
    }

    /**
     * @return array{enabled: bool, rupiah_per_point: int, point_value_amount: int, min_redeem_points: int}
     */
    public function settings(): array
    {
        $s = $this->loyalty->settings();

        return ['enabled' => $s->enabled, 'rupiah_per_point' => $s->rupiahPerPoint, 'point_value_amount' => $s->pointValueAmount, 'min_redeem_points' => $s->minRedeemPoints];
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
