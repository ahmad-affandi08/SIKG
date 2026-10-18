<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Booking;

/**
 * Pengaturan booking online satu klinik. `slug` adalah bagian alamat halaman publik (/book/{slug}) dan tidak memuat data pasien.
 * `otpTemplate` adalah nama templat WhatsApp berkode verifikasi; tanpa itu (atau tanpa WhatsApp API), permintaan menunggu
 * konfirmasi staf yang menghubungi pasien.
 */
final readonly class BookingSettings
{
    public const SLUG_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])$/';

    public function __construct(
        public bool $enabled,
        public string $slug,
        public int $slotMinutes,
        public int $horizonDays,
        public int $minLeadHours,
        public int $holdHours,
        public int $maxActivePerPhone,
        public ?string $otpTemplate,
        public ?string $welcomeText,
        public int $maxRequestsPerHour = 30,
    ) {}

    public static function defaults(): self
    {
        return new self(false, '', 30, 30, 2, 24, 2, null, null);
    }

    /**
     * @throws InvalidBooking
     */
    public function assertValid(): void
    {
        if ($this->slug !== '' && preg_match(self::SLUG_PATTERN, $this->slug) !== 1) {
            throw new InvalidBooking('booking.slug_invalid', 'Alamat halaman berisi huruf kecil, angka, dan tanda hubung (3 sampai 60 karakter).');
        }
        if ($this->enabled && $this->slug === '') {
            throw new InvalidBooking('booking.slug_required', 'Isi alamat halaman sebelum mengaktifkan booking online.');
        }
        if ($this->slotMinutes < 15 || $this->slotMinutes > 180 || $this->slotMinutes % 5 !== 0) {
            throw new InvalidBooking('booking.settings_invalid', 'Durasi slot 15 sampai 180 menit, kelipatan 5.');
        }
        if ($this->horizonDays < 1 || $this->horizonDays > 90 || $this->minLeadHours < 0 || $this->minLeadHours > 72 || $this->holdHours < 1 || $this->holdHours > 168 || $this->maxActivePerPhone < 1 || $this->maxActivePerPhone > 10 || $this->maxRequestsPerHour < 5 || $this->maxRequestsPerHour > 500) {
            throw new InvalidBooking('booking.settings_invalid', 'Batas hari, jeda minimum, masa tahan, batas per nomor, atau batas permintaan per jam di luar rentang yang diizinkan.');
        }
    }

    public function hasOtp(): bool
    {
        return $this->otpTemplate !== null && trim($this->otpTemplate) !== '';
    }
}
