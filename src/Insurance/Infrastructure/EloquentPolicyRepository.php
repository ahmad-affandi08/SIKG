<?php

declare(strict_types=1);

namespace Src\Insurance\Infrastructure;

use Src\Insurance\Domain\ClaimStatus;
use Src\Insurance\Domain\PolicyRecord;
use Src\Insurance\Domain\PolicyRepository;
use Src\Insurance\Infrastructure\Eloquent\ClaimModel;
use Src\Insurance\Infrastructure\Eloquent\PatientPolicyModel;

final class EloquentPolicyRepository implements PolicyRepository
{
    public function find(string $id): ?PolicyRecord
    {
        $m = PatientPolicyModel::query()->find($id);

        return $m === null ? null : $this->record($m);
    }

    public function forPatient(string $patientId): array
    {
        return array_values(PatientPolicyModel::query()->where('patient_id', $patientId)->orderByDesc('is_active')->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (PatientPolicyModel $m) => $this->record($m))->all());
    }

    public function add(string $actorId, string $patientId, string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): string
    {
        $m = new PatientPolicyModel;
        $m->forceFill([
            'patient_id' => $patientId, 'insurer_id' => $insurerId, 'policy_no' => $policyNo, 'ceiling_amount' => $ceilingAmount,
            'valid_from' => $validFrom, 'valid_to' => $validTo, 'notes' => $notes, 'is_active' => true, 'created_by' => $actorId,
        ])->save();

        return $m->id;
    }

    public function update(string $actorId, string $id, string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): void
    {
        PatientPolicyModel::query()->findOrFail($id)->forceFill([
            'insurer_id' => $insurerId, 'policy_no' => $policyNo, 'ceiling_amount' => $ceilingAmount,
            'valid_from' => $validFrom, 'valid_to' => $validTo, 'notes' => $notes, 'updated_by' => $actorId,
        ])->save();
    }

    public function setActive(string $actorId, string $id, bool $active): void
    {
        PatientPolicyModel::query()->findOrFail($id)->forceFill(['is_active' => $active, 'updated_by' => $actorId])->save();
    }

    public function committedAmount(string $policyId, ?string $exceptClaimId = null): int
    {
        $total = 0;
        foreach (ClaimModel::query()->where('policy_id', $policyId)->when($exceptClaimId !== null, fn ($q) => $q->where('id', '!=', $exceptClaimId))->get() as $claim) {
            if (ClaimStatus::from($claim->status)->counts()) {
                $total += (int) ($claim->approved_amount ?? $claim->claimed_amount);
            }
        }

        return $total;
    }

    private function record(PatientPolicyModel $m): PolicyRecord
    {
        return new PolicyRecord($m->id, $m->patient_id, $m->insurer_id, $m->policy_no, $m->ceiling_amount, $m->valid_from?->toDateString(), $m->valid_to?->toDateString(), $m->notes, $m->is_active);
    }
}
