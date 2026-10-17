<?php

declare(strict_types=1);

namespace Src\Lab\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Contracts\EncounterChargeGateway;
use Src\Clinic\Contracts\ClinicCalendar;
use Src\Clinic\Contracts\DoctorDirectory;
use Src\Clinical\Contracts\EncounterLookup;
use Src\Lab\Domain\InvalidLabOrder;
use Src\Lab\Domain\LaboratoryDirectory;
use Src\Lab\Domain\LabOrder;
use Src\Lab\Domain\LabOrderRepository;
use Src\Lab\Domain\WorkType;
use Src\Patient\Contracts\PatientLookup;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Transaction;
use Src\Shared\Domain\IdGenerator;

/**
 * P2-06: pesanan lab gigi (mahkota, gigi tiruan, aligner, dan sejenisnya). Satu use case, satu transaksi, pesanan dikunci selama
 * perubahan. Pekerjaan yang diterima dari lab dapat ditagihkan sekali ke pasien sebagai item tagihan kunjungan lewat Billing
 * (salinan jumlah, M7). Setiap langkah tercatat di audit (M10). Izin dicek di Presentation (M9).
 */
final readonly class LabOrderService
{
    public function __construct(
        private LabOrderRepository $orders,
        private LaboratoryDirectory $laboratories,
        private PatientLookup $patients,
        private DoctorDirectory $doctors,
        private EncounterLookup $encounters,
        private EncounterChargeGateway $charges,
        private LabOrderNumbering $numbering,
        private Transaction $tx,
        private AuditLogger $audit,
        private IdGenerator $ids,
        private ClinicCalendar $calendar,
        private Clock $clock,
    ) {}

    /**
     * @throws InvalidLabOrder
     */
    public function place(string $actorId, string $patientId, string $doctorId, string $laboratoryId, string $workType, string $toothNotes, string $description, ?string $shade, ?string $dueDate, int $labCostAmount, int $patientChargeAmount): string
    {
        $this->patients->exists($patientId) || throw new InvalidLabOrder('lab.not_found', 'Pasien tidak ditemukan.');
        $doctor = $this->doctors->find($doctorId);
        if ($doctor === null || ! $doctor->active) {
            throw new InvalidLabOrder('lab.doctor_invalid', 'Dokter tidak ditemukan atau nonaktif.');
        }
        $type = WorkType::tryFrom($workType) ?? throw new InvalidLabOrder('lab.type_invalid', 'Jenis pekerjaan tidak dikenal.');
        $due = $dueDate === null || $dueDate === '' ? null : $this->validDate($dueDate, 'Target selesai tidak valid.');

        return $this->tx->run(function () use ($actorId, $patientId, $doctorId, $laboratoryId, $type, $toothNotes, $description, $shade, $due, $labCostAmount, $patientChargeAmount): string {
            if (! $this->laboratories->isActive($laboratoryId)) {
                throw new InvalidLabOrder('lab.laboratory_invalid', 'Laboratorium tidak ditemukan atau nonaktif.');
            }
            $order = LabOrder::place($this->ids->generate(), $this->numbering->next(), $patientId, $doctorId, $laboratoryId, $type, mb_substr($toothNotes, 0, 100), mb_substr($description, 0, 500), $shade === null ? null : mb_substr($shade, 0, 30), $this->today(), $due, $labCostAmount, $patientChargeAmount);
            $this->orders->add($order, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'lab.order_placed', 'patient', $patientId, null, [
                'order_id' => $order->id, 'order_no' => $order->orderNo, 'work_type' => $type->value, 'lab_cost' => $labCostAmount, 'patient_charge' => $patientChargeAmount,
            ]));

            return $order->id;
        });
    }

    public function send(string $actorId, string $orderId, string $date): void
    {
        $date = $this->validDate($date, 'Tanggal kirim tidak valid.');
        $this->change($actorId, $orderId, 'lab.order_sent', function (LabOrder $order) use ($date): array {
            $order->send($date);

            return ['sent_date' => $date];
        });
    }

    public function receive(string $actorId, string $orderId, string $date, int $actualLabCostAmount): void
    {
        $date = $this->validDate($date, 'Tanggal terima tidak valid.');
        $this->change($actorId, $orderId, 'lab.order_received', function (LabOrder $order) use ($date, $actualLabCostAmount): array {
            $order->receive($date, $actualLabCostAmount);

            return ['received_date' => $date, 'lab_cost' => $actualLabCostAmount];
        });
    }

    public function cancel(string $actorId, string $orderId, string $reason): void
    {
        $this->change($actorId, $orderId, 'lab.order_cancelled', function (LabOrder $order) use ($reason): array {
            $order->cancel($reason);

            return [];
        });
    }

    /**
     * Menagihkan harga ke pasien ke tagihan kunjungan yang sedang berjalan.
     *
     * @throws InvalidLabOrder
     */
    public function charge(string $actorId, string $orderId, string $encounterId): void
    {
        $this->tx->run(function () use ($actorId, $orderId, $encounterId): void {
            $order = $this->orders->lock($orderId) ?? throw new InvalidLabOrder('lab.not_found', 'Pesanan lab tidak ditemukan.');
            $encounter = $this->encounters->find($encounterId);
            if ($encounter === null || $encounter->patientId !== $order->patientId || ! $encounter->open) {
                throw new InvalidLabOrder('lab.encounter_invalid', 'Pilih kunjungan pasien ini yang masih berjalan.');
            }
            if ($order->status()->value !== 'received') {
                throw new InvalidLabOrder('lab.not_received', 'Biaya baru dapat ditagihkan setelah pekerjaan diterima dari lab.');
            }
            if ($order->isBilled()) {
                throw new InvalidLabOrder('lab.already_billed', 'Pesanan ini sudah ditagihkan ke pasien.');
            }
            if ($order->patientChargeAmount() < 1) {
                throw new InvalidLabOrder('lab.no_charge', 'Harga ke pasien nol; tidak ada yang ditagihkan.');
            }

            $invoiceId = $this->charges->addCharge($encounterId, $order->patientId, 'lab', $order->id, mb_substr("Pekerjaan lab {$order->workType->label()} ({$order->orderNo})", 0, 255), $order->patientChargeAmount());
            $order->markBilled($invoiceId);
            $this->orders->save($order, $actorId);
            $this->audit->record(new AuditEntry($actorId, 'lab.order_charged', 'patient', $order->patientId, null, ['order_id' => $order->id, 'amount' => $order->patientChargeAmount(), 'invoice_id' => $invoiceId, 'encounter_id' => $encounterId]));
        });
    }

    /**
     * @param  non-empty-string  $action
     * @param  callable(LabOrder): array<string, mixed>  $apply
     */
    private function change(string $actorId, string $orderId, string $action, callable $apply): void
    {
        $this->tx->run(function () use ($actorId, $orderId, $action, $apply): void {
            $order = $this->orders->lock($orderId) ?? throw new InvalidLabOrder('lab.not_found', 'Pesanan lab tidak ditemukan.');
            $before = $order->status()->value;
            $detail = $apply($order);
            $this->orders->save($order, $actorId);
            $this->audit->record(new AuditEntry($actorId, $action, 'patient', $order->patientId, ['status' => $before], ['order_id' => $order->id, 'status' => $order->status()->value, ...$detail]));
        });
    }

    private function validDate(string $date, string $message): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidLabOrder('lab.date_invalid', $message);
        }

        return $date;
    }

    private function today(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($this->calendar->timezone()))->format('Y-m-d');
    }
}
