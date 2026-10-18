<?php

declare(strict_types=1);

namespace Src\Scheduling\Domain\Booking;

use Src\Shared\Domain\DomainException;

/**
 * Permintaan booking online yang melanggar aturan (slot tidak tersedia, batas per nomor, kode salah, dan sebagainya).
 */
final class InvalidBooking extends DomainException
{
    /**
     * @param  non-empty-string  $businessCode
     */
    public function __construct(string $businessCode, string $message)
    {
        parent::__construct($businessCode, $message);
    }

    /** Permintaan yang tidak ditemukan (token salah) menjadi 404 agar token tidak dapat ditebak lewat pesan galat. */
    public function httpStatus(): int
    {
        return $this->businessCode() === 'booking.not_found' ? 404 : parent::httpStatus();
    }
}
