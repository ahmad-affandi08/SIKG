<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Tanda vital (RM-02). Semua bilangan bulat: suhu dalam persepuluh derajat Celsius (368 = 36,8 C) agar tidak ada
 * bilangan pecahan. Setiap nilai opsional.
 */
final readonly class Vitals
{
    public function __construct(
        public ?int $systolic = null,
        public ?int $diastolic = null,
        public ?int $pulse = null,
        public ?int $temperatureTenths = null,
    ) {
        self::within($systolic, 40, 300, 'Tekanan sistolik');
        self::within($diastolic, 20, 200, 'Tekanan diastolik');
        self::within($pulse, 20, 300, 'Nadi');
        self::within($temperatureTenths, 300, 450, 'Suhu');
        if (($systolic === null) !== ($diastolic === null)) {
            throw new InvalidClinicalData('clinical.invalid_vitals', 'Tekanan darah diisi sistolik dan diastolik sekaligus.');
        }
        if ($systolic !== null && $diastolic !== null && $diastolic >= $systolic) {
            throw new InvalidClinicalData('clinical.invalid_vitals', 'Tekanan diastolik harus lebih rendah dari sistolik.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $int = fn (string $key): ?int => isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : null;

        return new self($int('systolic'), $int('diastolic'), $int('pulse'), $int('temperature_tenths'));
    }

    /**
     * @return array{systolic: ?int, diastolic: ?int, pulse: ?int, temperature_tenths: ?int}
     */
    public function toArray(): array
    {
        return ['systolic' => $this->systolic, 'diastolic' => $this->diastolic, 'pulse' => $this->pulse, 'temperature_tenths' => $this->temperatureTenths];
    }

    private static function within(?int $value, int $min, int $max, string $label): void
    {
        if ($value !== null && ($value < $min || $value > $max)) {
            throw new InvalidClinicalData('clinical.invalid_vitals', "{$label} di luar rentang yang wajar ({$min} sampai {$max}).");
        }
    }
}
