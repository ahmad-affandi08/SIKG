<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

/**
 * Polis atau keanggotaan penjamin milik pasien, dengan plafon (kosong berarti tanpa batas) dan masa berlaku.
 */
final readonly class PolicyRecord
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $insurerId,
        public string $policyNo,
        public ?int $ceilingAmount,
        public ?string $validFrom,
        public ?string $validTo,
        public ?string $notes,
        public bool $active,
    ) {}

    /** Berlaku pada tanggal lokal klinik (YYYY-MM-DD): aktif dan di dalam masa berlaku. */
    public function validOn(string $date): bool
    {
        return $this->active && ($this->validFrom === null || $this->validFrom <= $date) && ($this->validTo === null || $this->validTo >= $date);
    }
}
