<?php

declare(strict_types=1);

namespace Src\Insurance\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Contracts\InvoiceClaimGateway;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Insurance\Domain\Claim;
use Src\Insurance\Domain\ClaimReceipt;
use Src\Insurance\Domain\ClaimRepository;
use Src\Insurance\Domain\InsurerDirectory;
use Src\Insurance\Domain\InvalidClaim;
use Src\Insurance\Domain\PolicyRepository;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;
use Src\Shared\Domain\Money;

/**
 * P2-02: siklus klaim ke penjamin. Satu use case, satu transaksi, klaim dikunci selama perubahan. Jumlah klaim dibatasi total
 * tagihan dan plafon polis; uang yang diterima dari penjamin dicatat sebagai pembayaran pada tagihan lewat Billing dengan kunci
 * idempoten (M7). Setiap langkah tercatat di audit (M10). Izin dicek di Presentation (M9).
 */
final readonly class ClaimService
{
    public function __construct(
        private ClaimRepository $claims,
        private PolicyRepository $policies,
        private InsurerDirectory $insurers,
        private InvoiceClaimGateway $invoices,
        private ClaimNumbering $numbering,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    public function create(string $actorId, string $invoiceId, string $insurerId, ?string $policyId, int $claimedAmount, ?string $notes): string
    {
        return $this->tx->run(function () use ($actorId, $invoiceId, $insurerId, $policyId, $claimedAmount, $notes): string {
            $invoice = $this->invoices->find($invoiceId) ?? throw new InvalidClaim('claim.invoice_not_found', 'Tagihan tidak ditemukan.');
            if (! $invoice->claimable()) {
                throw new InvalidClaim('claim.invoice_not_claimable', 'Tagihan harus sudah terbit dan belum dibatalkan untuk ditagihkan ke penjamin.');
            }
            $insurer = $this->insurers->find($insurerId);
            if ($insurer === null || ! $insurer->active) {
                throw new InvalidClaim('insurer.not_found', 'Penjamin tidak ditemukan atau nonaktif.');
            }
            if ($claimedAmount <= 0) {
                throw new InvalidClaim('claim.amount_invalid', 'Jumlah klaim harus lebih dari nol.');
            }
            if ($this->claims->committedForInvoice($invoiceId) + $claimedAmount > $invoice->totalAmount) {
                throw new InvalidClaim('claim.exceeds_invoice', 'Total klaim melebihi total tagihan.');
            }

            if ($policyId !== null) {
                $policy = $this->policies->find($policyId) ?? throw new InvalidClaim('policy.not_found', 'Polis tidak ditemukan.');
                if ($policy->patientId !== $invoice->patientId || $policy->insurerId !== $insurerId) {
                    throw new InvalidClaim('claim.policy_mismatch', 'Polis bukan milik pasien tagihan ini atau bukan dari penjamin yang dipilih.');
                }
                if (! $policy->validOn($this->today())) {
                    throw new InvalidClaim('claim.policy_expired', 'Polis tidak aktif atau di luar masa berlaku.');
                }
                if ($policy->ceilingAmount !== null && $this->policies->committedAmount($policy->id) + $claimedAmount > $policy->ceilingAmount) {
                    throw new InvalidClaim('claim.ceiling_exceeded', 'Klaim melebihi sisa plafon polis.');
                }
            }

            $claim = Claim::draft($this->ids->generate(), $this->numbering->next(), $invoiceId, $invoice->patientId, $insurerId, $policyId, Money::rupiah($claimedAmount), $notes);
            $this->claims->add($claim, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'insurance.claim_created', 'invoice', $invoiceId, null, ['claim_id' => $claim->id, 'claim_no' => $claim->claimNo, 'insurer_id' => $insurerId, 'amount' => $claimedAmount]));

            return $claim->id;
        });
    }

    public function submit(string $actorId, string $claimId): void
    {
        $this->change($actorId, $claimId, 'insurance.claim_submitted', function (Claim $claim): array {
            $claim->submit($this->clock->now());

            return [];
        });
    }

    public function cancel(string $actorId, string $claimId): void
    {
        $this->change($actorId, $claimId, 'insurance.claim_cancelled', function (Claim $claim): array {
            $claim->cancel();

            return [];
        });
    }

    public function approve(string $actorId, string $claimId, int $approvedAmount, ?string $note): void
    {
        $this->change($actorId, $claimId, 'insurance.claim_approved', function (Claim $claim) use ($approvedAmount, $note): array {
            $claim->approve(Money::rupiah($approvedAmount), $note, $this->clock->now());

            return ['approved_amount' => $approvedAmount];
        });
    }

    public function reject(string $actorId, string $claimId, string $reason): void
    {
        $this->change($actorId, $claimId, 'insurance.claim_rejected', function (Claim $claim) use ($reason): array {
            $claim->reject($reason, $this->clock->now());

            return [];
        });
    }

    /**
     * Mencatat uang yang diterima dari penjamin. Idempoten terhadap kunci: pengulangan tidak menambah apa pun.
     */
    public function receive(string $actorId, string $claimId, int $amount, string $methodId, string $idempotencyKey, ?string $referenceNo): void
    {
        $this->tx->run(function () use ($actorId, $claimId, $amount, $methodId, $idempotencyKey, $referenceNo): void {
            $claim = $this->claims->lock($claimId) ?? throw new InvalidClaim('claim.not_found', 'Klaim tidak ditemukan.');
            if ($this->claims->findReceipt($claimId, $idempotencyKey) !== null) {
                return;
            }

            $now = $this->clock->now();
            $before = ['status' => $claim->status()->value, 'paid_amount' => $claim->paid()->amount];
            $claim->receive(Money::rupiah($amount), $now);
            $reference = $referenceNo === null || trim($referenceNo) === '' ? null : mb_substr(trim($referenceNo), 0, 100);

            // Uang masuk juga menjadi pembayaran pada tagihan; ditolak Billing (contoh melebihi sisa) membatalkan seluruh transaksi.
            $this->invoices->recordPayment($actorId, $claim->invoiceId, $methodId, $amount, 'ins:'.substr(sha1($claimId.'|'.$idempotencyKey), 0, 40), $reference ?? $claim->claimNo);
            $this->claims->addReceipt(new ClaimReceipt($this->ids->generate(), $claimId, $amount, $methodId, $reference, $idempotencyKey, $now, $actorId));
            $this->claims->save($claim, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'insurance.claim_received', 'invoice', $claim->invoiceId, $before, ['claim_id' => $claimId, 'amount' => $amount, 'status' => $claim->status()->value, 'paid_amount' => $claim->paid()->amount]));
        });
    }

    /**
     * @param  non-empty-string  $action
     * @param  callable(Claim): array<string, mixed>  $change  mengembalikan data tambahan untuk audit
     */
    private function change(string $actorId, string $claimId, string $action, callable $change): void
    {
        $this->tx->run(function () use ($actorId, $claimId, $action, $change): void {
            $claim = $this->claims->lock($claimId) ?? throw new InvalidClaim('claim.not_found', 'Klaim tidak ditemukan.');
            $before = $claim->status()->value;
            $extra = $change($claim);
            $this->claims->save($claim, $actorId);
            $this->audit->record(new AuditEntry($actorId, $action, 'invoice', $claim->invoiceId, ['status' => $before], ['claim_id' => $claim->id, 'claim_no' => $claim->claimNo, 'status' => $claim->status()->value, ...$extra]));
        });
    }

    private function today(): string
    {
        return (new DateTimeImmutable($this->clock->now()->format('c')))->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
