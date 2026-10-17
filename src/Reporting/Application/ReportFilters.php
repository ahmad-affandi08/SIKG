<?php

declare(strict_types=1);

namespace Src\Reporting\Application;

use Src\Reporting\Domain\DateRange;

/**
 * Penyaring yang dikirim pengguna: rentang tanggal (semua laporan), dokter dan metode bayar (laporan keuangan),
 * dan penyajian harian atau bulanan.
 */
final readonly class ReportFilters
{
    public const GRAIN_DAY = 'day';

    public const GRAIN_MONTH = 'month';

    public function __construct(
        public DateRange $range,
        public ?string $doctorId = null,
        public ?string $methodId = null,
        public string $grain = self::GRAIN_DAY,
    ) {}

    /**
     * @return array{from: string, to: string, doctor_id: string|null, method_id: string|null, grain: string}
     */
    public function toArray(): array
    {
        return ['from' => $this->range->from, 'to' => $this->range->to, 'doctor_id' => $this->doctorId, 'method_id' => $this->methodId, 'grain' => $this->grain];
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = fn (mixed $v): ?string => is_string($v) && $v !== '' ? $v : null;

        return new self(
            DateRange::between((string) ($data['from'] ?? ''), (string) ($data['to'] ?? '')),
            $string($data['doctor_id'] ?? null),
            $string($data['method_id'] ?? null),
            ($data['grain'] ?? null) === self::GRAIN_MONTH ? self::GRAIN_MONTH : self::GRAIN_DAY,
        );
    }
}
