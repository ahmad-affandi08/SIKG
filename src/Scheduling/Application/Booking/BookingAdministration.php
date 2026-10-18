<?php

declare(strict_types=1);

namespace Src\Scheduling\Application\Booking;

use Src\Scheduling\Domain\Booking\BookingRepository;
use Src\Scheduling\Domain\Booking\BookingSettings;
use Src\Scheduling\Domain\Booking\InvalidBooking;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Application\Transaction;

/**
 * P3-01: pengaturan booking online (alamat halaman, durasi slot, batas hari, masa tahan, batas per nomor, templat kode WhatsApp).
 */
final readonly class BookingAdministration
{
    public function __construct(private BookingRepository $bookings, private AuditLogger $audit, private Transaction $tx) {}

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $s = $this->bookings->settings();

        return [
            'enabled' => $s->enabled, 'slug' => $s->slug, 'slot_minutes' => $s->slotMinutes, 'horizon_days' => $s->horizonDays, 'min_lead_hours' => $s->minLeadHours,
            'hold_hours' => $s->holdHours, 'max_active_per_phone' => $s->maxActivePerPhone, 'otp_template' => $s->otpTemplate ?? '', 'welcome_text' => $s->welcomeText ?? '',
        ];
    }

    public function save(string $actorId, BookingSettings $next): void
    {
        $next->assertValid();
        $this->tx->run(function () use ($actorId, $next): void {
            if ($next->slug !== '' && $this->bookings->slugTaken($next->slug, true)) {
                throw new InvalidBooking('booking.slug_taken', 'Alamat halaman itu sudah dipakai. Pilih alamat lain.');
            }
            $before = $this->bookings->settings();
            $this->bookings->saveSettings($actorId, $next);
            $this->audit->record(new AuditEntry($actorId, 'booking.settings_changed', 'clinic', 'booking', $this->view($before), $this->view($next)));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function view(BookingSettings $s): array
    {
        return ['enabled' => $s->enabled, 'slug' => $s->slug, 'slot_minutes' => $s->slotMinutes, 'horizon_days' => $s->horizonDays, 'min_lead_hours' => $s->minLeadHours, 'hold_hours' => $s->holdHours, 'max_active_per_phone' => $s->maxActivePerPhone, 'otp_template' => $s->otpTemplate];
    }
}
