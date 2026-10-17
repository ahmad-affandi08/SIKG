<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

use InvalidArgumentException;
use Src\Shared\Domain\NumberPattern;

/**
 * Pengaturan klinik (SYS-04, AUTH-04): format nomor RM dan invoice, jam operasional per hari, batas sesi tidak aktif.
 * Zona waktu disimpan di kolom `clinics.timezone`. Hari: 1 = Senin sampai 7 = Minggu.
 */
final readonly class ClinicSettings
{
    public const DEFAULT_SESSION_MINUTES = 30;

    public const MIN_SESSION_MINUTES = 5;

    /**
     * @param  array<int, array{open: string, close: string}|null>  $operatingHours  null = tutup
     */
    public function __construct(
        public int $sessionTimeoutMinutes,
        public NumberPattern $medicalRecordPattern,
        public NumberPattern $invoicePattern,
        public array $operatingHours,
    ) {
        if ($sessionTimeoutMinutes < self::MIN_SESSION_MINUTES) {
            throw new InvalidArgumentException('Batas sesi minimal '.self::MIN_SESSION_MINUTES.' menit.');
        }
        for ($day = 1; $day <= 7; $day++) {
            if (! array_key_exists($day, $operatingHours)) {
                throw new InvalidArgumentException('Jam operasional harus mencakup hari 1 sampai 7.');
            }
            $hours = $operatingHours[$day];
            if ($hours !== null && ! self::validRange($hours['open'], $hours['close'])) {
                throw new InvalidArgumentException('Jam buka harus lebih awal dari jam tutup (format HH:MM).');
            }
        }
    }

    public static function defaults(): self
    {
        $weekday = ['open' => '08:00', 'close' => '17:00'];

        return new self(
            sessionTimeoutMinutes: self::DEFAULT_SESSION_MINUTES,
            medicalRecordPattern: new NumberPattern('RM-{seq:6}'),
            invoicePattern: new NumberPattern('INV-{yyyy}-{seq:5}'),
            operatingHours: [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday, 6 => $weekday, 7 => null],
        );
    }

    /**
     * @param  array<string, mixed>|null  $data  nilai tersimpan; bagian yang hilang memakai bawaan
     */
    public static function fromArray(?array $data): self
    {
        $defaults = self::defaults();
        if ($data === null) {
            return $defaults;
        }

        $hours = $defaults->operatingHours;
        if (isset($data['operating_hours']) && is_array($data['operating_hours'])) {
            foreach ($data['operating_hours'] as $day => $value) {
                $hours[(int) $day] = is_array($value) ? ['open' => (string) $value['open'], 'close' => (string) $value['close']] : null;
            }
        }

        return new self(
            sessionTimeoutMinutes: isset($data['session_timeout_minutes']) ? (int) $data['session_timeout_minutes'] : $defaults->sessionTimeoutMinutes,
            medicalRecordPattern: isset($data['medical_record_pattern']) ? new NumberPattern((string) $data['medical_record_pattern']) : $defaults->medicalRecordPattern,
            invoicePattern: isset($data['invoice_pattern']) ? new NumberPattern((string) $data['invoice_pattern']) : $defaults->invoicePattern,
            operatingHours: $hours,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_timeout_minutes' => $this->sessionTimeoutMinutes,
            'medical_record_pattern' => $this->medicalRecordPattern->pattern,
            'invoice_pattern' => $this->invoicePattern->pattern,
            'operating_hours' => $this->operatingHours,
        ];
    }

    public function isOpenOn(int $isoWeekday): bool
    {
        return ($this->operatingHours[$isoWeekday] ?? null) !== null;
    }

    private static function validRange(string $open, string $close): bool
    {
        $pattern = '/^([01]\d|2[0-3]):[0-5]\d$/';

        return preg_match($pattern, $open) === 1 && preg_match($pattern, $close) === 1 && $open < $close;
    }
}
