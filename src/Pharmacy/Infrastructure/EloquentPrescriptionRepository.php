<?php

declare(strict_types=1);

namespace Src\Pharmacy\Infrastructure;

use DateTimeImmutable;
use Src\Pharmacy\Domain\PrescriptionItemInput;
use Src\Pharmacy\Domain\PrescriptionItemRecord;
use Src\Pharmacy\Domain\PrescriptionRecord;
use Src\Pharmacy\Domain\PrescriptionRepository;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionItemModel;
use Src\Pharmacy\Infrastructure\Eloquent\PrescriptionModel;

final class EloquentPrescriptionRepository implements PrescriptionRepository
{
    public function add(string $actorId, string $encounterId, string $patientId, string $doctorId, DateTimeImmutable $at, ?string $allergyAcknowledged): string
    {
        $row = new PrescriptionModel;
        $row->forceFill([
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'issued_at' => $at,
            'status' => 'issued',
            'allergy_acknowledged' => $allergyAcknowledged,
            'created_by' => $actorId,
        ])->save();

        return $row->id;
    }

    public function addItem(string $actorId, string $prescriptionId, PrescriptionItemInput $input, string $name, int $unitPriceAmount, ?DateTimeImmutable $dispensedAt): string
    {
        $row = new PrescriptionItemModel;
        $row->forceFill([
            'prescription_id' => $prescriptionId,
            'medicine_id' => $input->medicineId,
            'free_text_name' => $input->freeTextName,
            'medicine_name' => $name,
            'dose' => $input->dose,
            'frequency' => $input->frequency,
            'duration' => $input->duration,
            'instructions' => $input->instructions,
            'quantity' => $input->quantity,
            'dispense_in_clinic' => $input->dispenseInClinic,
            'unit_price_amount' => $unitPriceAmount,
            'dispensed_at' => $dispensedAt,
            'created_by' => $actorId,
        ])->save();

        return $row->id;
    }

    public function find(string $id): ?PrescriptionRecord
    {
        $row = PrescriptionModel::query()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function findForUpdate(string $id): ?PrescriptionRecord
    {
        $row = PrescriptionModel::query()->lockForUpdate()->find($id);

        return $row === null ? null : $this->record($row);
    }

    public function forEncounter(string $encounterId): array
    {
        return array_values(PrescriptionModel::query()->where('encounter_id', $encounterId)->orderBy('issued_at')->orderBy('id')->get()
            ->map(fn (PrescriptionModel $p) => $this->record($p))->all());
    }

    public function void(string $actorId, string $id, string $reason): void
    {
        PrescriptionModel::query()->whereKey($id)->update(['status' => 'void', 'void_reason' => $reason, 'updated_by' => $actorId, 'updated_at' => now()]);
    }

    private function record(PrescriptionModel $p): PrescriptionRecord
    {
        $items = PrescriptionItemModel::query()->where('prescription_id', $p->id)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn (PrescriptionItemModel $i) => new PrescriptionItemRecord($i->id, $i->medicine_id, $i->medicine_name, $i->dose, $i->frequency, $i->duration, $i->instructions, $i->quantity, $i->dispense_in_clinic, $i->unit_price_amount))->all();

        return new PrescriptionRecord($p->id, $p->encounter_id, $p->patient_id, $p->doctor_id, $p->issued_at->toDateTimeImmutable(), $p->status, $p->void_reason, array_values($items));
    }
}
