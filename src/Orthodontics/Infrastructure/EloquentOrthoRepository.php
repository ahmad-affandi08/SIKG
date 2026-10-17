<?php

declare(strict_types=1);

namespace Src\Orthodontics\Infrastructure;

use DateTimeImmutable;
use Src\Orthodontics\Domain\Appliance;
use Src\Orthodontics\Domain\CaseStatus;
use Src\Orthodontics\Domain\ControlRecord;
use Src\Orthodontics\Domain\InstallmentRecord;
use Src\Orthodontics\Domain\OrthoCase;
use Src\Orthodontics\Domain\OrthoRepository;
use Src\Orthodontics\Infrastructure\Eloquent\OrthoCaseModel;
use Src\Orthodontics\Infrastructure\Eloquent\OrthoControlModel;
use Src\Orthodontics\Infrastructure\Eloquent\OrthoInstallmentModel;

final class EloquentOrthoRepository implements OrthoRepository
{
    public function addCase(OrthoCase $case, array $schedule, string $actorId): void
    {
        $m = new OrthoCaseModel;
        $m->id = $case->id;
        $m->forceFill([...$this->values($case), 'patient_id' => $case->patientId, 'doctor_id' => $case->doctorId, 'created_by' => $actorId])->save();
        foreach ($schedule as $row) {
            $i = new OrthoInstallmentModel;
            $i->forceFill(['case_id' => $case->id, 'seq' => $row['seq'], 'due_date' => $row['due_date'], 'amount' => $row['amount'], 'status' => InstallmentRecord::SCHEDULED])->save();
        }
    }

    public function find(string $id): ?OrthoCase
    {
        $m = OrthoCaseModel::query()->find($id);

        return $m === null ? null : $this->case($m);
    }

    public function lock(string $id): ?OrthoCase
    {
        $m = OrthoCaseModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->case($m);
    }

    public function save(OrthoCase $case, string $actorId): void
    {
        OrthoCaseModel::query()->findOrFail($case->id)->forceFill([...$this->values($case), 'updated_by' => $actorId])->save();
    }

    public function list(?string $status, ?string $patientId, int $limit): array
    {
        return array_values(OrthoCaseModel::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($patientId !== null, fn ($q) => $q->where('patient_id', $patientId))
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()->map(fn (OrthoCaseModel $m) => $this->case($m))->all());
    }

    public function installments(string $caseId): array
    {
        return array_values(OrthoInstallmentModel::query()->where('case_id', $caseId)->orderBy('seq')->get()->map(fn (OrthoInstallmentModel $m) => $this->installment($m))->all());
    }

    public function findInstallment(string $id): ?InstallmentRecord
    {
        $m = OrthoInstallmentModel::query()->find($id);

        return $m === null ? null : $this->installment($m);
    }

    public function lockInstallment(string $id): ?InstallmentRecord
    {
        $m = OrthoInstallmentModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->installment($m);
    }

    public function markInvoiced(string $installmentId, string $encounterId, string $invoiceId, DateTimeImmutable $at): void
    {
        OrthoInstallmentModel::query()->findOrFail($installmentId)->forceFill(['status' => InstallmentRecord::INVOICED, 'encounter_id' => $encounterId, 'invoice_id' => $invoiceId, 'invoiced_at' => $at])->save();
    }

    public function cancelOpenInstallments(string $caseId): int
    {
        return OrthoInstallmentModel::query()->where('case_id', $caseId)->where('status', InstallmentRecord::SCHEDULED)->update(['status' => InstallmentRecord::CANCELLED]);
    }

    public function addControl(string $actorId, string $caseId, string $controlDate, ?string $encounterId, ?string $wireOrTray, ?string $adjustments, ?string $notes, ?string $nextControlDate): string
    {
        $m = new OrthoControlModel;
        $m->forceFill([
            'case_id' => $caseId, 'control_date' => $controlDate, 'encounter_id' => $encounterId, 'wire_or_tray' => $wireOrTray,
            'adjustments' => $adjustments, 'notes' => $notes, 'next_control_date' => $nextControlDate, 'created_by' => $actorId,
        ])->save();

        return $m->id;
    }

    public function controls(string $caseId): array
    {
        return array_values(OrthoControlModel::query()->where('case_id', $caseId)->orderByDesc('control_date')->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn (OrthoControlModel $m) => $this->control($m))->all());
    }

    public function latestControls(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }
        $out = [];
        foreach (OrthoControlModel::query()->whereIn('case_id', $caseIds)->orderBy('control_date')->orderBy('created_at')->orderBy('id')->get() as $m) {
            $out[$m->case_id] = $this->control($m);
        }

        return $out;
    }

    public function unbilledAmounts(array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        return OrthoInstallmentModel::query()->whereIn('case_id', $caseIds)->where('status', InstallmentRecord::SCHEDULED)
            ->selectRaw('case_id, sum(amount) as total')->groupBy('case_id')->pluck('total', 'case_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(OrthoCase $case): array
    {
        return [
            'appliance' => $case->appliance->value, 'title' => $case->title, 'plan_notes' => $case->planNotes, 'total_fee_amount' => $case->totalFeeAmount,
            'start_date' => $case->startDate, 'expected_months' => $case->expectedMonths, 'status' => $case->status()->value,
            'ended_at' => $case->endedAt(), 'ended_note' => $case->endedNote(), 'version' => $case->version(),
        ];
    }

    private function case(OrthoCaseModel $m): OrthoCase
    {
        return new OrthoCase(
            $m->id, $m->patient_id, $m->doctor_id, Appliance::from($m->appliance), $m->title, $m->plan_notes, $m->total_fee_amount,
            $m->start_date->toDateString(), $m->expected_months, CaseStatus::from($m->status), $m->ended_at?->toDateTimeImmutable(), $m->ended_note, $m->version,
        );
    }

    private function installment(OrthoInstallmentModel $m): InstallmentRecord
    {
        return new InstallmentRecord($m->id, $m->case_id, $m->seq, $m->due_date->toDateString(), $m->amount, $m->status, $m->encounter_id, $m->invoice_id, $m->invoiced_at?->toDateTimeImmutable());
    }

    private function control(OrthoControlModel $m): ControlRecord
    {
        return new ControlRecord($m->id, $m->case_id, $m->control_date->toDateString(), $m->encounter_id, $m->wire_or_tray, $m->adjustments, $m->notes, $m->next_control_date?->toDateString(), $m->created_by);
    }
}
