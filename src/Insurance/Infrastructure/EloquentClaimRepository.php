<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure;

use Src\Insurance\Domain\Claim;
use Src\Insurance\Domain\ClaimReceipt;
use Src\Insurance\Domain\ClaimRepository;
use Src\Insurance\Domain\ClaimStatus;
use Src\Insurance\Infrastructure\Eloquent\ClaimModel;
use Src\Insurance\Infrastructure\Eloquent\ClaimReceiptModel;
use Src\Shared\Domain\Money;

final class EloquentClaimRepository implements ClaimRepository
{
    public function add(Claim $claim, string $actorId): void
    {
        $m = new ClaimModel;
        $m->id = $claim->id;
        $m->forceFill([...$this->values($claim), 'invoice_id' => $claim->invoiceId, 'patient_id' => $claim->patientId, 'insurer_id' => $claim->insurerId, 'policy_id' => $claim->policyId, 'claim_no' => $claim->claimNo, 'created_by' => $actorId])->save();
    }

    public function find(string $id): ?Claim
    {
        $m = ClaimModel::query()->find($id);

        return $m === null ? null : $this->claim($m);
    }

    public function lock(string $id): ?Claim
    {
        $m = ClaimModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->claim($m);
    }

    public function save(Claim $claim, string $actorId): void
    {
        ClaimModel::query()->findOrFail($claim->id)->forceFill([...$this->values($claim), 'updated_by' => $actorId])->save();
    }

    public function list(?string $status, ?string $insurerId, ?string $patientId, int $limit): array
    {
        return array_values(ClaimModel::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($insurerId !== null, fn ($q) => $q->where('insurer_id', $insurerId))
            ->when($patientId !== null, fn ($q) => $q->where('patient_id', $patientId))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (ClaimModel $m) => $this->claim($m))->all());
    }

    public function open(): array
    {
        return array_values(ClaimModel::query()->whereIn('status', [ClaimStatus::Submitted->value, ClaimStatus::Approved->value])->orderBy('submitted_at')->orderBy('id')->get()
            ->map(fn (ClaimModel $m) => $this->claim($m))->all());
    }

    public function committedForInvoice(string $invoiceId, ?string $exceptClaimId = null): int
    {
        $total = 0;
        foreach (ClaimModel::query()->where('invoice_id', $invoiceId)->when($exceptClaimId !== null, fn ($q) => $q->where('id', '!=', $exceptClaimId))->get() as $m) {
            if (ClaimStatus::from($m->status)->counts()) {
                $total += (int) ($m->approved_amount ?? $m->claimed_amount);
            }
        }

        return $total;
    }

    public function addReceipt(ClaimReceipt $receipt): void
    {
        $m = new ClaimReceiptModel;
        $m->id = $receipt->id;
        $m->forceFill([
            'claim_id' => $receipt->claimId, 'amount' => $receipt->amount, 'method_id' => $receipt->methodId, 'reference_no' => $receipt->referenceNo,
            'idempotency_key' => $receipt->idempotencyKey, 'received_at' => $receipt->receivedAt, 'received_by' => $receipt->receivedBy,
        ])->save();
    }

    public function findReceipt(string $claimId, string $idempotencyKey): ?ClaimReceipt
    {
        $m = ClaimReceiptModel::query()->where('claim_id', $claimId)->where('idempotency_key', $idempotencyKey)->first();

        return $m === null ? null : $this->receipt($m);
    }

    public function receipts(string $claimId): array
    {
        return array_values(ClaimReceiptModel::query()->where('claim_id', $claimId)->orderBy('received_at')->orderBy('id')->get()->map(fn (ClaimReceiptModel $m) => $this->receipt($m))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Claim $claim): array
    {
        return [
            'claimed_amount' => $claim->claimed()->amount,
            'approved_amount' => $claim->approved()?->amount,
            'paid_amount' => $claim->paid()->amount,
            'status' => $claim->status()->value,
            'notes' => $claim->notes(),
            'decision_note' => $claim->decisionNote(),
            'submitted_at' => $claim->submittedAt(),
            'decided_at' => $claim->decidedAt(),
            'paid_at' => $claim->paidAt(),
            'version' => $claim->version(),
        ];
    }

    private function claim(ClaimModel $m): Claim
    {
        return new Claim(
            $m->id, $m->claim_no, $m->invoice_id, $m->patient_id, $m->insurer_id, $m->policy_id,
            Money::rupiah($m->claimed_amount), $m->approved_amount === null ? null : Money::rupiah($m->approved_amount), Money::rupiah($m->paid_amount),
            ClaimStatus::from($m->status), $m->notes, $m->decision_note,
            $m->submitted_at?->toDateTimeImmutable(), $m->decided_at?->toDateTimeImmutable(), $m->paid_at?->toDateTimeImmutable(), $m->version,
        );
    }

    private function receipt(ClaimReceiptModel $m): ClaimReceipt
    {
        return new ClaimReceipt($m->id, $m->claim_id, $m->amount, $m->method_id, $m->reference_no, $m->idempotency_key, $m->received_at->toDateTimeImmutable(), $m->received_by);
    }
}
