<?php

declare(strict_types=1);

namespace Src\Scheduling\Application\Booking;

use Src\Scheduling\Domain\Booking\BookingRepository;

/**
 * P3-01: dari alamat halaman publik (/book/{slug}) ke klinik pemiliknya. Hanya klinik yang mengaktifkan booking online yang dikenali.
 */
final readonly class BookingPortalResolver
{
    public function __construct(private BookingRepository $bookings) {}

    public function clinicFor(string $slug): ?string
    {
        return $this->bookings->resolveSlug($slug)['clinic_id'] ?? null;
    }
}
