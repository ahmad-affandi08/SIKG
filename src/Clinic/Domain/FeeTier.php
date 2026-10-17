<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Satu tingkat jasa bertingkat (P2-09): persentase (basis poin) untuk bagian produksi bulanan dokter sampai batas `upTo` rupiah.
 * Tingkat terakhir tanpa batas (`upTo` kosong) berlaku untuk semua produksi di atas batas sebelumnya.
 */
final readonly class FeeTier
{
    public function __construct(public ?int $upTo, public int $rateBasisPoints)
    {
        if ($rateBasisPoints < 0 || $rateBasisPoints > 10_000) {
            throw new InvalidDoctorData('doctor.invalid_fee', 'Persentase tingkat jasa harus antara 0 dan 100.');
        }
        if ($upTo !== null && ($upTo < 1 || $upTo > FeeRule::MAX_AMOUNT * 10)) {
            throw new InvalidDoctorData('doctor.invalid_fee', 'Batas tingkat jasa harus rupiah bulat lebih dari nol.');
        }
    }

    /**
     * @return array{up_to: int|null, rate_bp: int}
     */
    public function toArray(): array
    {
        return ['up_to' => $this->upTo, 'rate_bp' => $this->rateBasisPoints];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $upTo = $data['up_to'] ?? null;
        $rate = $data['rate_bp'] ?? null;
        if (($upTo !== null && ! is_int($upTo)) || ! is_int($rate)) {
            throw new InvalidDoctorData('doctor.invalid_fee', 'Tingkat jasa tidak valid.');
        }

        return new self($upTo, $rate);
    }
}
