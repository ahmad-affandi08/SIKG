<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

/**
 * Satu aturan jasa. Persentase dalam basis poin (3000 = 30%) agar tidak ada angka pecahan; nominal dalam rupiah bulat (M4).
 */
final readonly class FeeRule
{
    public const MAX_AMOUNT = 9_999_999_999;

    public function __construct(public FeeMode $mode, public int $value)
    {
        $max = $mode === FeeMode::Percentage ? 10_000 : self::MAX_AMOUNT;
        if ($value < 0 || $value > $max) {
            throw new InvalidDoctorData('doctor.invalid_fee', $mode === FeeMode::Percentage
                ? 'Persentase jasa harus antara 0 dan 100.'
                : 'Nominal jasa tidak valid.');
        }
    }

    /**
     * Jasa untuk satu tindakan bertarif `price` rupiah, dibulatkan ke bawah ke rupiah.
     */
    public function feeFor(int $price): int
    {
        return $this->mode === FeeMode::Percentage
            ? intdiv($price * $this->value, 10_000)
            : $this->value;
    }

    /**
     * @return array{mode: string, value: int}
     */
    public function toArray(): array
    {
        return ['mode' => $this->mode->value, 'value' => $this->value];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $mode = FeeMode::tryFrom((string) ($data['mode'] ?? ''));
        $value = $data['value'] ?? null;
        if ($mode === null || ! is_int($value)) {
            throw new InvalidDoctorData('doctor.invalid_fee', 'Aturan jasa tidak valid.');
        }

        return new self($mode, $value);
    }
}
