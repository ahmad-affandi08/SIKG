<?php

declare(strict_types=1);

namespace Src\Insurance\Application;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Insurance\Domain\InsurerDirectory;
use Src\Insurance\Domain\InvalidClaim;
use Src\Insurance\Domain\PolicyRepository;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;

/**
 * P2-02: polis penjamin per pasien (nomor, plafon, masa berlaku). Plafon yang tersisa dihitung dari klaim yang masih dihitung,
 * bukan diisi manual. Perubahan tercatat di audit (M10); polis tidak dihapus, hanya dinonaktifkan (M5).
 */
final readonly class PolicyService
{
    public function __construct(
        private PolicyRepository $policies,
        private InsurerDirectory $insurers,
        private PatientLookup $patients,
        private AccessLogger $access,
        private AuditLogger $audit,
        private Transaction $tx,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{patient: array{id: string, name: string, medical_record_no: string}, policies: list<array<string, mixed>>, insurers: list<array{value: string, label: string}>}
     */
    public function forPatient(string $patientId, string $route): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new InvalidClaim('patient.not_found', 'Pasien tidak ditemukan.');
        $this->access->patientRecordOpened($patientId, $route);

        $records = $this->policies->forPatient($patientId);
        $insurers = $this->insurers->byIds(array_values(array_unique(array_map(fn ($p) => $p->insurerId, $records))));
        $today = $this->today();

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'policies' => array_map(function ($p) use ($insurers, $today): array {
                $used = $this->policies->committedAmount($p->id);

                return [
                    'id' => $p->id,
                    'insurer_id' => $p->insurerId,
                    'insurer_name' => $insurers[$p->insurerId]->name ?? '-',
                    'policy_no' => $p->policyNo,
                    'ceiling_amount' => $p->ceilingAmount,
                    'used_amount' => $used,
                    'remaining_amount' => $p->ceilingAmount === null ? null : max(0, $p->ceilingAmount - $used),
                    'valid_from' => $p->validFrom,
                    'valid_to' => $p->validTo,
                    'notes' => $p->notes,
                    'active' => $p->active,
                    'valid_now' => $p->validOn($today),
                ];
            }, $records),
            'insurers' => array_map(fn ($i) => ['value' => $i->id, 'label' => $i->name.' ('.$i->typeLabel().')'], $this->insurers->active()),
        ];
    }

    public function create(string $actorId, string $patientId, string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): string
    {
        $this->patients->exists($patientId) || throw new InvalidClaim('patient.not_found', 'Pasien tidak ditemukan.');
        [$policyNo, $notes] = $this->validate($insurerId, $policyNo, $ceilingAmount, $validFrom, $validTo, $notes);

        return $this->tx->run(function () use ($actorId, $patientId, $insurerId, $policyNo, $ceilingAmount, $validFrom, $validTo, $notes): string {
            $id = $this->policies->add($actorId, $patientId, $insurerId, $policyNo, $ceilingAmount, $validFrom, $validTo, $notes);
            $this->audit->record(new AuditEntry($actorId, 'insurance.policy_created', 'patient', $patientId, null, ['policy_id' => $id, 'insurer_id' => $insurerId, 'ceiling_amount' => $ceilingAmount]));

            return $id;
        });
    }

    public function update(string $actorId, string $policyId, string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): void
    {
        $before = $this->policies->find($policyId) ?? throw new InvalidClaim('policy.not_found', 'Polis tidak ditemukan.');
        [$policyNo, $notes] = $this->validate($insurerId, $policyNo, $ceilingAmount, $validFrom, $validTo, $notes);

        $this->tx->run(function () use ($actorId, $before, $policyId, $insurerId, $policyNo, $ceilingAmount, $validFrom, $validTo, $notes): void {
            $this->policies->update($actorId, $policyId, $insurerId, $policyNo, $ceilingAmount, $validFrom, $validTo, $notes);
            $this->audit->record(new AuditEntry(
                $actorId, 'insurance.policy_updated', 'patient', $before->patientId,
                ['policy_id' => $policyId, 'insurer_id' => $before->insurerId, 'ceiling_amount' => $before->ceilingAmount, 'valid_to' => $before->validTo],
                ['policy_id' => $policyId, 'insurer_id' => $insurerId, 'ceiling_amount' => $ceilingAmount, 'valid_to' => $validTo],
            ));
        });
    }

    public function setActive(string $actorId, string $policyId, bool $active): void
    {
        $policy = $this->policies->find($policyId) ?? throw new InvalidClaim('policy.not_found', 'Polis tidak ditemukan.');

        $this->tx->run(function () use ($actorId, $policy, $active): void {
            $this->policies->setActive($actorId, $policy->id, $active);
            $this->audit->record(new AuditEntry($actorId, $active ? 'insurance.policy_activated' : 'insurance.policy_deactivated', 'patient', $policy->patientId, null, ['policy_id' => $policy->id]));
        });
    }

    /**
     * @return array{0: string, 1: string|null} nomor polis dan catatan yang sudah dibersihkan
     */
    private function validate(string $insurerId, string $policyNo, ?int $ceilingAmount, ?string $validFrom, ?string $validTo, ?string $notes): array
    {
        $insurer = $this->insurers->find($insurerId);
        if ($insurer === null || ! $insurer->active) {
            throw new InvalidClaim('insurer.not_found', 'Penjamin tidak ditemukan atau nonaktif.');
        }
        $policyNo = trim($policyNo);
        if ($policyNo === '' || mb_strlen($policyNo) > 60) {
            throw new InvalidClaim('policy.number_required', 'Nomor polis atau kartu anggota wajib diisi (maksimal 60 karakter).');
        }
        if ($ceilingAmount !== null && $ceilingAmount < 0) {
            throw new InvalidClaim('policy.ceiling_invalid', 'Plafon tidak boleh negatif.');
        }
        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            throw new InvalidClaim('policy.period_invalid', 'Masa berlaku akhir tidak boleh sebelum awal.');
        }
        $notes = $notes === null || trim($notes) === '' ? null : mb_substr(trim($notes), 0, 500);

        return [$policyNo, $notes];
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
