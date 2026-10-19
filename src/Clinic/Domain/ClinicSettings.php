<?php

declare(strict_types=1);

namespace Src\Clinic\Domain;

use InvalidArgumentException;
use Src\Shared\Domain\NumberPattern;
use Src\Shared\Domain\ServiceProfile;
use Src\Shared\Domain\WhatsAppNumber;

/**
 * Pengaturan klinik (SYS-04, AUTH-04, KSR-02): format nomor RM dan invoice, jam operasional per hari, batas sesi tidak aktif,
 * batas diskon per peran (persen bulat), tarif pajak tagihan (KSR-09, persen bulat, bawaan 0), serta tujuan notifikasi (SYS-05,
 * JDW-07): nomor WhatsApp staf dan sakelar pengingat janji untuk pasien, serta profil layanan klinik (KLU-01).
 * Zona waktu disimpan di kolom `clinics.timezone`. Hari: 1 = Senin sampai 7 = Minggu.
 */
final readonly class ClinicSettings
{
    public const DEFAULT_SESSION_MINUTES = 30;

    public const MIN_SESSION_MINUTES = 5;

    public const MAX_NOTIFY_NUMBERS = 5;

    /** Batas diskon bawaan per peran dalam persen; peran yang tidak tercantum tidak boleh memberi diskon. */
    public const DEFAULT_DISCOUNT_LIMITS = ['pemilik' => 100, 'admin' => 100, 'kasir' => 10];

    /**
     * @param  array<int, array{open: string, close: string}|null>  $operatingHours  null = tutup
     * @param  array<string, int>  $discountLimits  peran => batas diskon persen (0 sampai 100)
     * @param  list<string>  $notifyWhatsapp  nomor WhatsApp staf penerima pemberitahuan (bentuk 628..., tanpa duplikat)
     */
    public function __construct(
        public int $sessionTimeoutMinutes,
        public NumberPattern $medicalRecordPattern,
        public NumberPattern $invoicePattern,
        public array $operatingHours,
        public array $discountLimits = self::DEFAULT_DISCOUNT_LIMITS,
        public int $taxPercent = 0,
        public array $notifyWhatsapp = [],
        public bool $remindersEnabled = true,
        public ServiceProfile $serviceProfile = ServiceProfile::Dental,
    ) {
        if (count($notifyWhatsapp) > self::MAX_NOTIFY_NUMBERS) {
            throw new InvalidArgumentException('Nomor WhatsApp staf maksimal '.self::MAX_NOTIFY_NUMBERS.'.');
        }
        foreach ($notifyWhatsapp as $number) {
            if (WhatsAppNumber::tryFrom($number)?->value !== $number) {
                throw new InvalidArgumentException('Nomor WhatsApp staf harus nomor Indonesia (08..., 628..., atau +628...).');
            }
        }
        if ($taxPercent < 0 || $taxPercent > 100) {
            throw new InvalidArgumentException('Tarif pajak harus antara 0 dan 100 persen.');
        }
        if ($sessionTimeoutMinutes < self::MIN_SESSION_MINUTES) {
            throw new InvalidArgumentException('Batas sesi minimal '.self::MIN_SESSION_MINUTES.' menit.');
        }
        foreach ($discountLimits as $limit) {
            if ($limit < 0 || $limit > 100) {
                throw new InvalidArgumentException('Batas diskon harus antara 0 dan 100 persen.');
            }
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

    /**
     * Mengubah isian bebas (satu nomor per baris atau dipisah koma/titik koma) menjadi daftar nomor 628... tanpa duplikat.
     *
     * @param  list<string>  $inputs
     * @return list<string>
     *
     * @throws InvalidArgumentException bila ada nomor yang tidak sah
     */
    public static function normalizeNumbers(array $inputs): array
    {
        $numbers = [];
        foreach ($inputs as $input) {
            foreach (preg_split('/[\r\n,;]+/', $input) ?: [] as $part) {
                if (trim($part) === '') {
                    continue;
                }
                $number = WhatsAppNumber::tryFrom($part)
                    ?? throw new InvalidArgumentException('Nomor WhatsApp staf harus nomor Indonesia (08..., 628..., atau +628...).');
                $numbers[$number->value] = $number->value;
            }
        }

        return array_values($numbers);
    }

    public static function defaults(): self
    {
        $weekday = ['open' => '08:00', 'close' => '17:00'];

        return new self(
            sessionTimeoutMinutes: self::DEFAULT_SESSION_MINUTES,
            medicalRecordPattern: new NumberPattern('RM-{seq:6}'),
            invoicePattern: new NumberPattern('INV-{yyyy}-{seq:5}'),
            operatingHours: [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday, 6 => $weekday, 7 => null],
            discountLimits: self::DEFAULT_DISCOUNT_LIMITS,
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

        $limits = $defaults->discountLimits;
        if (isset($data['discount_limits']) && is_array($data['discount_limits'])) {
            foreach ($data['discount_limits'] as $role => $value) {
                $limits[(string) $role] = (int) $value;
            }
        }

        return new self(
            sessionTimeoutMinutes: isset($data['session_timeout_minutes']) ? (int) $data['session_timeout_minutes'] : $defaults->sessionTimeoutMinutes,
            medicalRecordPattern: isset($data['medical_record_pattern']) ? new NumberPattern((string) $data['medical_record_pattern']) : $defaults->medicalRecordPattern,
            invoicePattern: isset($data['invoice_pattern']) ? new NumberPattern((string) $data['invoice_pattern']) : $defaults->invoicePattern,
            operatingHours: $hours,
            discountLimits: $limits,
            taxPercent: isset($data['tax_percent']) ? (int) $data['tax_percent'] : $defaults->taxPercent,
            notifyWhatsapp: isset($data['notify_whatsapp']) && is_array($data['notify_whatsapp']) ? array_values(array_map('strval', $data['notify_whatsapp'])) : [],
            remindersEnabled: ! isset($data['reminders_enabled']) || (bool) $data['reminders_enabled'],
            serviceProfile: isset($data['service_profile']) ? (ServiceProfile::tryFrom((string) $data['service_profile']) ?? ServiceProfile::default()) : ServiceProfile::default(),
        );
    }

    public function withServiceProfile(ServiceProfile $profile): self
    {
        return new self(
            $this->sessionTimeoutMinutes, $this->medicalRecordPattern, $this->invoicePattern, $this->operatingHours,
            $this->discountLimits, $this->taxPercent, $this->notifyWhatsapp, $this->remindersEnabled, $profile,
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
            'discount_limits' => $this->discountLimits,
            'tax_percent' => $this->taxPercent,
            'notify_whatsapp' => $this->notifyWhatsapp,
            'reminders_enabled' => $this->remindersEnabled,
            'service_profile' => $this->serviceProfile->value,
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
