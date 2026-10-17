<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

use DateTimeImmutable;

/**
 * Paket perawatan ortodonti seorang pasien (P2-04). Berjalan sampai diselesaikan atau dihentikan (dengan alasan); setelah itu
 * tidak menerima kontrol baru dan cicilan yang belum ditagihkan dibatalkan.
 */
final class OrthoCase
{
    public function __construct(
        public readonly string $id,
        public readonly string $patientId,
        public readonly string $doctorId,
        public readonly Appliance $appliance,
        public readonly string $title,
        public readonly ?string $planNotes,
        public readonly int $totalFeeAmount,
        public readonly string $startDate,
        public readonly int $expectedMonths,
        private CaseStatus $status,
        private ?DateTimeImmutable $endedAt,
        private ?string $endedNote,
        private int $version,
    ) {}

    public static function open(string $id, string $patientId, string $doctorId, Appliance $appliance, string $title, ?string $planNotes, int $totalFeeAmount, string $startDate, int $expectedMonths): self
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 150) {
            throw new InvalidOrthodontics('ortho.title_required', 'Nama paket wajib diisi (maksimal 150 karakter).');
        }
        if ($totalFeeAmount <= 0) {
            throw new InvalidOrthodontics('ortho.fee_invalid', 'Biaya paket harus lebih dari nol.');
        }
        if ($expectedMonths < 1 || $expectedMonths > 60) {
            throw new InvalidOrthodontics('ortho.months_invalid', 'Perkiraan lama perawatan 1 sampai 60 bulan.');
        }
        $notes = $planNotes === null || trim($planNotes) === '' ? null : mb_substr(trim($planNotes), 0, 1000);

        return new self($id, $patientId, $doctorId, $appliance, $title, $notes, $totalFeeAmount, $startDate, $expectedMonths, CaseStatus::Active, null, null, 1);
    }

    public function status(): CaseStatus
    {
        return $this->status;
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function endedNote(): ?string
    {
        return $this->endedNote;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function isActive(): bool
    {
        return $this->status === CaseStatus::Active;
    }

    public function assertActive(): void
    {
        if (! $this->isActive()) {
            throw new InvalidOrthodontics('ortho.not_active', 'Paket perawatan ini sudah '.mb_strtolower($this->status->label()).'.');
        }
    }

    public function complete(DateTimeImmutable $at, ?string $note): void
    {
        $this->assertActive();
        $this->status = CaseStatus::Completed;
        $this->endedAt = $at;
        $this->endedNote = $this->clean($note);
        $this->version++;
    }

    public function stop(DateTimeImmutable $at, string $reason): void
    {
        $this->assertActive();
        if (trim($reason) === '') {
            throw new InvalidOrthodontics('ortho.reason_required', 'Alasan menghentikan perawatan wajib diisi.');
        }
        $this->status = CaseStatus::Stopped;
        $this->endedAt = $at;
        $this->endedNote = $this->clean($reason);
        $this->version++;
    }

    private function clean(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === null || $text === '' ? null : mb_substr($text, 0, 255);
    }
}
