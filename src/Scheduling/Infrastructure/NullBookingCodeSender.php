<?php

declare(strict_types=1);

namespace Src\Scheduling\Infrastructure;

use Src\Scheduling\Contracts\BookingCodeNotSent;
use Src\Scheduling\Contracts\BookingCodeSender;

/** Bawaan: tidak ada saluran kode verifikasi, sehingga booking menunggu konfirmasi staf. */
final class NullBookingCodeSender implements BookingCodeSender
{
    public function available(): bool
    {
        return false;
    }

    public function send(string $phone, string $code, string $template): void
    {
        throw new BookingCodeNotSent('Saluran kode verifikasi belum tersedia.');
    }
}
