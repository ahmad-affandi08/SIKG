<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Money;

/**
 * Klaim (tagihan) ke penjamin atas satu tagihan pasien (P2-02). Status dan jumlah diturunkan oleh domain, bukan diisi manual (M7):
 * draft -> diajukan -> disetujui (jumlah disetujui <= jumlah diklaim) -> lunas bila semua jumlah disetujui sudah diterima,
 * atau ditolak. Penerimaan dapat bertahap. Piutang = yang masih harus dibayar penjamin.
 */
final class Claim
{
    public function __construct(
        public readonly string $id,
        public readonly string $claimNo,
        public readonly string $invoiceId,
        public readonly string $patientId,
        public readonly string $insurerId,
        public readonly ?string $policyId,
        private readonly Money $claimed,
        private ?Money $approved,
        private Money $paid,
        private ClaimStatus $status,
        private ?string $notes,
        private ?string $decisionNote,
        private ?DateTimeImmutable $submittedAt,
        private ?DateTimeImmutable $decidedAt,
        private ?DateTimeImmutable $paidAt,
        private int $version,
    ) {}

    public static function draft(string $id, string $claimNo, string $invoiceId, string $patientId, string $insurerId, ?string $policyId, Money $claimed, ?string $notes): self
    {
        if ($claimed->amount <= 0) {
            throw new InvalidClaim('claim.amount_invalid', 'Jumlah klaim harus lebih dari nol.');
        }

        return new self($id, $claimNo, $invoiceId, $patientId, $insurerId, $policyId, $claimed, null, Money::zero(), ClaimStatus::Draft, self::clean($notes), null, null, null, null, 1);
    }

    public function claimed(): Money
    {
        return $this->claimed;
    }

    public function approved(): ?Money
    {
        return $this->approved;
    }

    public function paid(): Money
    {
        return $this->paid;
    }

    public function status(): ClaimStatus
    {
        return $this->status;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function decisionNote(): ?string
    {
        return $this->decisionNote;
    }

    public function submittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** Jumlah yang menjadi dasar tagihan ke penjamin: disetujui bila sudah diputuskan, selain itu diklaim. */
    public function basis(): Money
    {
        return $this->approved ?? $this->claimed;
    }

    /** Piutang penjamin: yang belum diterima dari klaim yang masih hidup. */
    public function outstanding(): Money
    {
        return match ($this->status) {
            ClaimStatus::Submitted => $this->claimed->subtract($this->paid),
            ClaimStatus::Approved => $this->basis()->subtract($this->paid),
            default => Money::zero(),
        };
    }

    public function submit(DateTimeImmutable $at): void
    {
        $this->require(ClaimStatus::Draft, 'claim.not_draft', 'Hanya klaim draft yang dapat diajukan.');
        $this->status = ClaimStatus::Submitted;
        $this->submittedAt = $at;
        $this->version++;
    }

    public function cancel(): void
    {
        $this->require(ClaimStatus::Draft, 'claim.not_draft', 'Hanya klaim draft yang dapat dibatalkan.');
        $this->status = ClaimStatus::Cancelled;
        $this->version++;
    }

    public function approve(Money $amount, ?string $note, DateTimeImmutable $at): void
    {
        $this->require(ClaimStatus::Submitted, 'claim.not_submitted', 'Hanya klaim yang sudah diajukan yang dapat diputuskan.');
        if ($amount->amount <= 0 || $amount->amount > $this->claimed->amount) {
            throw new InvalidClaim('claim.approved_invalid', 'Jumlah disetujui harus lebih dari nol dan tidak melebihi jumlah diklaim.');
        }
        $this->approved = $amount;
        $this->decisionNote = self::clean($note, 255);
        $this->decidedAt = $at;
        $this->status = ClaimStatus::Approved;
        $this->version++;
    }

    public function reject(string $reason, DateTimeImmutable $at): void
    {
        $this->require(ClaimStatus::Submitted, 'claim.not_submitted', 'Hanya klaim yang sudah diajukan yang dapat diputuskan.');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidClaim('claim.reason_required', 'Alasan penolakan wajib diisi.');
        }
        $this->decisionNote = mb_substr($reason, 0, 255);
        $this->decidedAt = $at;
        $this->status = ClaimStatus::Rejected;
        $this->version++;
    }

    /**
     * Penerimaan uang dari penjamin. Hanya untuk klaim disetujui dan tidak melebihi sisa yang disetujui.
     */
    public function receive(Money $amount, DateTimeImmutable $at): void
    {
        $this->require(ClaimStatus::Approved, 'claim.not_approved', 'Pembayaran hanya dapat dicatat untuk klaim yang sudah disetujui.');
        if ($amount->amount <= 0) {
            throw new InvalidClaim('claim.amount_invalid', 'Jumlah pembayaran harus lebih dari nol.');
        }
        if ($amount->amount > $this->basis()->subtract($this->paid)->amount) {
            throw new InvalidClaim('claim.overpaid', 'Pembayaran melebihi sisa yang disetujui penjamin.');
        }
        $this->paid = $this->paid->add($amount);
        if ($this->paid->amount === $this->basis()->amount) {
            $this->status = ClaimStatus::Paid;
            $this->paidAt = $at;
        }
        $this->version++;
    }

    /**
     * @param  non-empty-string  $code
     */
    private function require(ClaimStatus $status, string $code, string $message): void
    {
        if ($this->status !== $status) {
            throw new InvalidClaim($code, $message);
        }
    }

    private static function clean(?string $text, int $max = 500): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === null || $text === '' ? null : mb_substr($text, 0, $max);
    }
}
