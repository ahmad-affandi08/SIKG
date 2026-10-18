<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Loyalty;

use Src\Billing\Domain\InvalidBillingData;

/**
 * Paket kunjungan milik pasien (sesi prabayar). Nama, harga, dan batas nilai per sesi disalin saat dibeli (M7). Satu sesi
 * menanggung satu satuan item tagihan sampai batas nilai per sesi.
 */
final readonly class PatientPackage
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $name,
        public int $sessionsTotal,
        public int $sessionsLeft,
        public ?int $maxSessionValueAmount,
        public ?string $expiresOn,
        public string $status,
    ) {}

    /**
     * @throws InvalidBillingData
     */
    public function assertUsable(string $today): void
    {
        if ($this->status !== 'active') {
            throw new InvalidBillingData('package.void', 'Paket ini sudah dibatalkan.');
        }
        if ($this->sessionsLeft < 1) {
            throw new InvalidBillingData('package.exhausted', 'Sesi paket sudah habis.');
        }
        if ($this->expiresOn !== null && $today > $this->expiresOn) {
            throw new InvalidBillingData('package.expired', 'Paket sudah melewati masa berlaku.');
        }
    }

    /** Nilai yang ditanggung satu sesi untuk item bernilai `unitPrice`. */
    public function coverageFor(int $unitPrice): int
    {
        return $this->maxSessionValueAmount === null ? $unitPrice : min($unitPrice, $this->maxSessionValueAmount);
    }
}
