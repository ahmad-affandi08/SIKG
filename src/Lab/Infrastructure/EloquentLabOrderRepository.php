<?php

declare(strict_types=1);

namespace Src\Lab\Infrastructure;

use Src\Lab\Domain\LabOrder;
use Src\Lab\Domain\LabOrderRepository;
use Src\Lab\Domain\LabOrderStatus;
use Src\Lab\Domain\WorkType;
use Src\Lab\Infrastructure\Eloquent\LabOrderModel;

final class EloquentLabOrderRepository implements LabOrderRepository
{
    public function add(LabOrder $order, string $actorId): void
    {
        $m = new LabOrderModel;
        $m->id = $order->id;
        $m->forceFill($this->values($order) + [
            'order_no' => $order->orderNo, 'patient_id' => $order->patientId, 'doctor_id' => $order->doctorId, 'laboratory_id' => $order->laboratoryId,
            'work_type' => $order->workType->value, 'tooth_notes' => $order->toothNotes, 'description' => $order->description, 'shade' => $order->shade,
            'ordered_date' => $order->orderedDate, 'due_date' => $order->dueDate, 'created_by' => $actorId,
        ])->save();
    }

    public function find(string $id): ?LabOrder
    {
        $m = LabOrderModel::query()->find($id);

        return $m === null ? null : $this->order($m);
    }

    public function lock(string $id): ?LabOrder
    {
        $m = LabOrderModel::query()->lockForUpdate()->find($id);

        return $m === null ? null : $this->order($m);
    }

    public function save(LabOrder $order, string $actorId): void
    {
        $m = LabOrderModel::query()->findOrFail($order->id);
        $m->forceFill($this->values($order) + ['updated_by' => $actorId, 'version' => $m->version + 1])->save();
    }

    public function list(?string $status, ?string $patientId, int $limit): array
    {
        return array_values(LabOrderModel::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->when($patientId !== null, fn ($q) => $q->where('patient_id', $patientId))
            ->orderByDesc('ordered_date')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (LabOrderModel $m) => $this->order($m))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function values(LabOrder $order): array
    {
        return [
            'status' => $order->status()->value,
            'sent_date' => $order->sentDate(),
            'received_date' => $order->receivedDate(),
            'lab_cost_amount' => $order->labCostAmount(),
            'patient_charge_amount' => $order->patientChargeAmount(),
            'invoice_id' => $order->invoiceId(),
            'cancel_note' => $order->cancelNote(),
        ];
    }

    private function order(LabOrderModel $m): LabOrder
    {
        return LabOrder::restore(
            $m->id, $m->order_no, $m->patient_id, $m->doctor_id, $m->laboratory_id, WorkType::from($m->work_type), $m->tooth_notes, $m->description, $m->shade,
            $m->ordered_date->toDateString(), $m->due_date?->toDateString(), LabOrderStatus::from($m->status), $m->sent_date?->toDateString(), $m->received_date?->toDateString(),
            $m->lab_cost_amount, $m->patient_charge_amount, $m->invoice_id, $m->cancel_note, $m->version,
        );
    }
}
