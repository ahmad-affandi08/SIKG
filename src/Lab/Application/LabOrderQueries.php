<?php

declare(strict_types=1);

namespace Src\Lab\Application;

use DateTimeZone;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Lab\Domain\InvalidLabOrder;
use Src\Lab\Domain\LaboratoryDirectory;
use Src\Lab\Domain\LabOrder;
use Src\Lab\Domain\LabOrderRepository;
use Src\Lab\Domain\LabOrderStatus;
use Src\Lab\Domain\WorkType;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AccessLogger;
use Src\Shared\Application\Clock;

/**
 * P2-06: daftar dan rincian pesanan lab serta data formulir. Uraian pekerjaan, gigi, warna, dan catatan hanya dikembalikan bila
 * pemanggil berhak membuka rekam medis; petugas kasir melihat pesanan, status, dan biayanya saja.
 */
final readonly class LabOrderQueries
{
    public function __construct(
        private LabOrderRepository $orders,
        private LaboratoryDirectory $laboratories,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private EncounterLookup $encounters,
        private AccessLogger $access,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @return array{orders: list<array<string, mixed>>, statuses: list<array{value: string, label: string}>}
     */
    public function list(?string $status): array
    {
        $filter = $status === null || $status === '' ? null : (LabOrderStatus::tryFrom($status)?->value);

        return [
            'orders' => $this->rows($this->orders->list($filter, null, 200), false),
            'statuses' => array_map(fn (LabOrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], LabOrderStatus::cases()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $orderId, bool $withClinical, string $route): array
    {
        $order = $this->orders->find($orderId) ?? throw new InvalidLabOrder('lab.not_found', 'Pesanan lab tidak ditemukan.');
        $this->access->patientRecordOpened($order->patientId, $route);
        [$row] = $this->rows([$order], $withClinical);

        $open = [];
        if ($order->status() === LabOrderStatus::Received && ! $order->isBilled()) {
            foreach ($this->encounters->openForPatient($order->patientId) as $encounter) {
                $open[] = ['value' => $encounter->id, 'label' => 'Kunjungan berjalan '.substr($encounter->id, -6)];
            }
        }

        return ['order' => $row, 'open_encounters' => $open, 'can_clinical' => $withClinical];
    }

    /**
     * @return array<string, mixed>
     */
    public function createForm(string $patientId): array
    {
        $patient = $this->patients->summaries([$patientId])[$patientId] ?? throw new InvalidLabOrder('lab.not_found', 'Pasien tidak ditemukan.');
        $this->access->patientRecordOpened($patientId, 'lab-orders.create');

        return [
            'patient' => ['id' => $patient->id, 'name' => $patient->name, 'medical_record_no' => $patient->medicalRecordNo],
            'doctors' => array_map(fn ($d) => ['value' => $d->id, 'label' => $d->name], $this->doctors->active()),
            'laboratories' => $this->laboratories->options(),
            'work_types' => array_map(fn (WorkType $t) => ['value' => $t->value, 'label' => $t->label()], WorkType::cases()),
            'today' => $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d'),
        ];
    }

    /**
     * @param  list<LabOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function rows(array $orders, bool $withClinical): array
    {
        $patients = $this->patients->summaries(array_values(array_unique(array_map(fn (LabOrder $o) => $o->patientId, $orders))));
        $doctors = $this->doctors->byIds(array_values(array_unique(array_map(fn (LabOrder $o) => $o->doctorId, $orders))));
        $labs = $this->laboratories->names(array_values(array_unique(array_map(fn (LabOrder $o) => $o->laboratoryId, $orders))));
        $today = $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');

        return array_map(fn (LabOrder $o): array => [
            'id' => $o->id,
            'order_no' => $o->orderNo,
            'patient_id' => $o->patientId,
            'patient_name' => $patients[$o->patientId]->name ?? '-',
            'medical_record_no' => $patients[$o->patientId]->medicalRecordNo ?? '-',
            'doctor_name' => $doctors[$o->doctorId]->name ?? '-',
            'laboratory_name' => $labs[$o->laboratoryId] ?? '-',
            'work_type_label' => $o->workType->label(),
            'status' => $o->status()->value,
            'status_label' => $o->status()->label(),
            'ordered_date' => $o->orderedDate,
            'due_date' => $o->dueDate,
            'sent_date' => $o->sentDate(),
            'received_date' => $o->receivedDate(),
            'overdue' => ($o->status() === LabOrderStatus::Ordered || $o->status() === LabOrderStatus::Sent) && $o->dueDate !== null && $o->dueDate < $today,
            'lab_cost_amount' => $o->labCostAmount(),
            'patient_charge_amount' => $o->patientChargeAmount(),
            'billed' => $o->isBilled(),
            'tooth_notes' => $withClinical ? $o->toothNotes : null,
            'description' => $withClinical ? $o->description : null,
            'shade' => $withClinical ? $o->shade : null,
            'cancel_note' => $withClinical ? $o->cancelNote() : null,
        ], $orders);
    }
}
