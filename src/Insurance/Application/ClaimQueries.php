<?php

declare(strict_types=1);

namespace Src\Insurance\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Contracts\InvoiceClaimGateway;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\PaymentMethodCatalog;
use Src\Insurance\Domain\Claim;
use Src\Insurance\Domain\ClaimRepository;
use Src\Insurance\Domain\ClaimStatus;
use Src\Insurance\Domain\InsurerDirectory;
use Src\Insurance\Domain\InvalidClaim;
use Src\Insurance\Domain\PolicyRepository;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\Clock;

/**
 * P2-02: baca klaim, formulir klaim dari tagihan, dan piutang penjamin (umur piutang dari tanggal pengajuan).
 */
final readonly class ClaimQueries
{
    private const BUCKETS = [['label' => '0-30 hari', 'max' => 30], ['label' => '31-60 hari', 'max' => 60], ['label' => '61-90 hari', 'max' => 90], ['label' => 'Lebih dari 90 hari', 'max' => PHP_INT_MAX]];

    public function __construct(
        private ClaimRepository $claims,
        private PolicyRepository $policies,
        private InsurerDirectory $insurers,
        private InvoiceClaimGateway $invoices,
        private PatientLookup $patients,
        private PaymentMethodCatalog $methods,
        private AccessLogger $access,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{claims: list<array<string, mixed>>, statuses: list<array{value: string, label: string}>, insurers: list<array{value: string, label: string}>}
     */
    public function list(?string $status, ?string $insurerId): array
    {
        $claims = $this->claims->list($status, $insurerId, null, 200);

        return [
            'claims' => $this->rows($claims),
            'statuses' => array_map(fn (ClaimStatus $s) => ['value' => $s->value, 'label' => $s->label()], ClaimStatus::cases()),
            'insurers' => array_map(fn ($i) => ['value' => $i->id, 'label' => $i->name], $this->insurers->active()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $claimId, string $route): array
    {
        $claim = $this->claims->find($claimId) ?? throw new InvalidClaim('claim.not_found', 'Klaim tidak ditemukan.');
        $this->access->patientRecordOpened($claim->patientId, $route);
        [$row] = $this->rows([$claim]);
        $policy = $claim->policyId === null ? null : $this->policies->find($claim->policyId);
        $status = $claim->status();

        return [
            'claim' => $row + [
                'notes' => $claim->notes(),
                'decision_note' => $claim->decisionNote(),
                'decided_at' => $claim->decidedAt()?->format('c'),
                'paid_at' => $claim->paidAt()?->format('c'),
                'policy_no' => $policy?->policyNo,
            ],
            'receipts' => array_map(fn ($r) => [
                'id' => $r->id,
                'amount' => $r->amount,
                'reference_no' => $r->referenceNo,
                'received_at' => $r->receivedAt->format('c'),
                'method_name' => $this->methods->find($r->methodId)->name ?? '-',
            ], $this->claims->receipts($claimId)),
            'methods' => array_map(fn ($m) => ['value' => $m->id, 'label' => $m->name], $this->methods->active()),
            'can' => [
                'submit' => $status === ClaimStatus::Draft,
                'cancel' => $status === ClaimStatus::Draft,
                'decide' => $status === ClaimStatus::Submitted,
                'receive' => $status === ClaimStatus::Approved,
            ],
        ];
    }

    /**
     * Data formulir klaim baru dari satu tagihan.
     *
     * @return array<string, mixed>
     */
    public function createForm(string $invoiceId): array
    {
        $invoice = $this->invoices->find($invoiceId) ?? throw new InvalidClaim('claim.invoice_not_found', 'Tagihan tidak ditemukan.');
        $patient = $this->patients->summaries([$invoice->patientId])[$invoice->patientId] ?? null;
        $committed = $this->claims->committedForInvoice($invoiceId);
        $today = $this->today();
        $insurers = $this->insurers->byIds(array_map(fn ($p) => $p->insurerId, $this->policies->forPatient($invoice->patientId)));

        return [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status_label' => $invoice->statusLabel,
                'claimable' => $invoice->claimable(),
                'total_amount' => $invoice->totalAmount,
                'paid_amount' => $invoice->paidAmount,
                'committed_amount' => $committed,
                'claimable_amount' => max(0, $invoice->totalAmount - $committed),
                'patient_name' => $patient->name ?? '-',
                'medical_record_no' => $patient->medicalRecordNo ?? '-',
            ],
            'policies' => array_values(array_map(fn ($p) => [
                'id' => $p->id,
                'insurer_id' => $p->insurerId,
                'label' => ($insurers[$p->insurerId]->name ?? '-').' - '.$p->policyNo,
                'remaining_amount' => $p->ceilingAmount === null ? null : max(0, $p->ceilingAmount - $this->policies->committedAmount($p->id)),
            ], array_filter($this->policies->forPatient($invoice->patientId), fn ($p) => $p->validOn($today)))),
            'insurers' => array_map(fn ($i) => ['value' => $i->id, 'label' => $i->name], $this->insurers->active()),
        ];
    }

    /**
     * Piutang penjamin per penjamin dengan umur piutang.
     *
     * @return array{total: int, count: int, buckets: list<array{label: string, amount: int}>, insurers: list<array<string, mixed>>}
     */
    public function receivables(): array
    {
        $open = array_values(array_filter($this->claims->open(), fn (Claim $c) => $c->outstanding()->amount > 0));
        $insurers = $this->insurers->byIds(array_values(array_unique(array_map(fn (Claim $c) => $c->insurerId, $open))));
        $now = $this->clock->now();

        $emptyBuckets = fn (): array => array_map(fn ($b) => ['label' => $b['label'], 'amount' => 0], self::BUCKETS);
        $totals = $emptyBuckets();
        $perInsurer = [];
        foreach ($open as $claim) {
            $days = $this->ageDays($claim->submittedAt(), $now);
            $index = 0;
            foreach (self::BUCKETS as $i => $bucket) {
                if ($days <= $bucket['max']) {
                    $index = $i;
                    break;
                }
            }
            $amount = $claim->outstanding()->amount;
            $row = $perInsurer[$claim->insurerId] ?? ['insurer_id' => $claim->insurerId, 'insurer_name' => $insurers[$claim->insurerId]->name ?? '-', 'count' => 0, 'outstanding' => 0, 'buckets' => $emptyBuckets()];
            $row['count']++;
            $row['outstanding'] += $amount;
            $row['buckets'][$index]['amount'] += $amount;
            $perInsurer[$claim->insurerId] = $row;
            $totals[$index]['amount'] += $amount;
        }
        usort($perInsurer, fn (array $a, array $b) => $b['outstanding'] <=> $a['outstanding']);

        return [
            'total' => array_sum(array_column($perInsurer, 'outstanding')),
            'count' => array_sum(array_column($perInsurer, 'count')),
            'buckets' => $totals,
            'insurers' => $perInsurer,
        ];
    }

    /**
     * @param  list<Claim>  $claims
     * @return list<array<string, mixed>>
     */
    private function rows(array $claims): array
    {
        $insurers = $this->insurers->byIds(array_values(array_unique(array_map(fn (Claim $c) => $c->insurerId, $claims))));
        $patients = $this->patients->summaries(array_values(array_unique(array_map(fn (Claim $c) => $c->patientId, $claims))));
        $invoices = $this->invoices->byIds(array_values(array_unique(array_map(fn (Claim $c) => $c->invoiceId, $claims))));

        return array_map(fn (Claim $c) => [
            'id' => $c->id,
            'claim_no' => $c->claimNo,
            'invoice_id' => $c->invoiceId,
            'invoice_number' => $invoices[$c->invoiceId]->number ?? null,
            'patient_name' => $patients[$c->patientId]->name ?? '-',
            'medical_record_no' => $patients[$c->patientId]->medicalRecordNo ?? '-',
            'insurer_name' => $insurers[$c->insurerId]->name ?? '-',
            'status' => $c->status()->value,
            'status_label' => $c->status()->label(),
            'claimed_amount' => $c->claimed()->amount,
            'approved_amount' => $c->approved()?->amount,
            'paid_amount' => $c->paid()->amount,
            'outstanding_amount' => $c->outstanding()->amount,
            'submitted_at' => $c->submittedAt()?->format('c'),
        ], $claims);
    }

    private function ageDays(?DateTimeImmutable $from, DateTimeImmutable $now): int
    {
        return $from === null ? 0 : max(0, (int) floor(($now->getTimestamp() - $from->getTimestamp()) / 86400));
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
