<?php

declare(strict_types=1);

namespace Src\Lab\Domain;

/**
 * P2-06: pesanan pekerjaan ke laboratorium gigi. ordered -> sent -> received; dibatalkan selama belum diterima. Biaya lab
 * (yang dibayar klinik) dan harga ke pasien dicatat terpisah dalam rupiah bulat (M4); selisihnya hak klinik, sedangkan biaya lab
 * menjadi bahan potongan jasa dokter (P2-09). Setelah ditagihkan ke pasien, nilai tidak berubah (M7).
 */
final class LabOrder
{
    public const MAX_AMOUNT = 999_999_999_999;

    private function __construct(
        public readonly string $id,
        public readonly string $orderNo,
        public readonly string $patientId,
        public readonly string $doctorId,
        public readonly string $laboratoryId,
        public readonly WorkType $workType,
        public readonly string $toothNotes,
        public readonly string $description,
        public readonly ?string $shade,
        public readonly string $orderedDate,
        public readonly ?string $dueDate,
        private LabOrderStatus $status,
        private ?string $sentDate,
        private ?string $receivedDate,
        private int $labCostAmount,
        private int $patientChargeAmount,
        private ?string $invoiceId,
        private ?string $cancelNote,
        public readonly int $version,
    ) {}

    public static function place(string $id, string $orderNo, string $patientId, string $doctorId, string $laboratoryId, WorkType $workType, string $toothNotes, string $description, ?string $shade, string $orderedDate, ?string $dueDate, int $labCostAmount, int $patientChargeAmount): self
    {
        $description = trim($description);
        if ($description === '') {
            throw new InvalidLabOrder('lab.description_required', 'Uraian pekerjaan wajib diisi.');
        }
        self::assertAmount($labCostAmount, 'Biaya lab');
        self::assertAmount($patientChargeAmount, 'Harga ke pasien');
        if ($dueDate !== null && $dueDate < $orderedDate) {
            throw new InvalidLabOrder('lab.due_before_order', 'Target selesai tidak boleh sebelum tanggal pesan.');
        }

        return new self($id, $orderNo, $patientId, $doctorId, $laboratoryId, $workType, trim($toothNotes), $description, $shade === null || trim($shade) === '' ? null : trim($shade), $orderedDate, $dueDate, LabOrderStatus::Ordered, null, null, $labCostAmount, $patientChargeAmount, null, null, 1);
    }

    public static function restore(string $id, string $orderNo, string $patientId, string $doctorId, string $laboratoryId, WorkType $workType, string $toothNotes, string $description, ?string $shade, string $orderedDate, ?string $dueDate, LabOrderStatus $status, ?string $sentDate, ?string $receivedDate, int $labCostAmount, int $patientChargeAmount, ?string $invoiceId, ?string $cancelNote, int $version): self
    {
        return new self($id, $orderNo, $patientId, $doctorId, $laboratoryId, $workType, $toothNotes, $description, $shade, $orderedDate, $dueDate, $status, $sentDate, $receivedDate, $labCostAmount, $patientChargeAmount, $invoiceId, $cancelNote, $version);
    }

    public function status(): LabOrderStatus
    {
        return $this->status;
    }

    public function sentDate(): ?string
    {
        return $this->sentDate;
    }

    public function receivedDate(): ?string
    {
        return $this->receivedDate;
    }

    public function labCostAmount(): int
    {
        return $this->labCostAmount;
    }

    public function patientChargeAmount(): int
    {
        return $this->patientChargeAmount;
    }

    public function invoiceId(): ?string
    {
        return $this->invoiceId;
    }

    public function cancelNote(): ?string
    {
        return $this->cancelNote;
    }

    public function isBilled(): bool
    {
        return $this->invoiceId !== null;
    }

    public function send(string $date): void
    {
        if ($this->status !== LabOrderStatus::Ordered) {
            throw new InvalidLabOrder('lab.not_ordered', 'Hanya pesanan yang baru dipesan yang dapat ditandai terkirim.');
        }
        if ($date < $this->orderedDate) {
            throw new InvalidLabOrder('lab.date_before_order', 'Tanggal kirim tidak boleh sebelum tanggal pesan.');
        }
        $this->status = LabOrderStatus::Sent;
        $this->sentDate = $date;
    }

    /**
     * Pekerjaan diterima dari lab; biaya aktual dari lab dapat berbeda dari perkiraan saat memesan. Harga ke pasien tidak berubah.
     */
    public function receive(string $date, int $actualLabCostAmount): void
    {
        if ($this->status !== LabOrderStatus::Sent) {
            throw new InvalidLabOrder('lab.not_sent', 'Pesanan harus dikirim ke lab dulu sebelum ditandai diterima.');
        }
        if ($this->sentDate !== null && $date < $this->sentDate) {
            throw new InvalidLabOrder('lab.date_before_sent', 'Tanggal terima tidak boleh sebelum tanggal kirim.');
        }
        self::assertAmount($actualLabCostAmount, 'Biaya lab');
        $this->status = LabOrderStatus::Received;
        $this->receivedDate = $date;
        $this->labCostAmount = $actualLabCostAmount;
    }

    public function cancel(string $reason): void
    {
        if ($this->status !== LabOrderStatus::Ordered && $this->status !== LabOrderStatus::Sent) {
            throw new InvalidLabOrder('lab.cannot_cancel', 'Pesanan yang sudah diterima atau dibatalkan tidak dapat dibatalkan.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidLabOrder('lab.reason_required', 'Alasan pembatalan wajib diisi.');
        }
        $this->status = LabOrderStatus::Cancelled;
        $this->cancelNote = mb_substr($reason, 0, 255);
    }

    /**
     * Pekerjaan yang sudah diterima dapat ditagihkan sekali ke pasien sebesar harga ke pasien.
     */
    public function markBilled(string $invoiceId): void
    {
        if ($this->status !== LabOrderStatus::Received) {
            throw new InvalidLabOrder('lab.not_received', 'Biaya baru dapat ditagihkan setelah pekerjaan diterima dari lab.');
        }
        if ($this->invoiceId !== null) {
            throw new InvalidLabOrder('lab.already_billed', 'Pesanan ini sudah ditagihkan ke pasien.');
        }
        if ($this->patientChargeAmount < 1) {
            throw new InvalidLabOrder('lab.no_charge', 'Harga ke pasien nol; tidak ada yang ditagihkan.');
        }
        $this->invoiceId = $invoiceId;
    }

    private static function assertAmount(int $amount, string $label): void
    {
        if ($amount < 0 || $amount > self::MAX_AMOUNT) {
            throw new InvalidLabOrder('lab.amount_invalid', $label.' harus bilangan bulat rupiah tidak negatif.');
        }
    }
}
