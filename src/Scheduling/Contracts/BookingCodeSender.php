<?php

declare(strict_types=1);

namespace Src\Scheduling\Contracts;

/**
 * Pengirim kode verifikasi nomor HP untuk booking online (P3-01). Implementasi ada di konteks Integration (WhatsApp Business API
 * milik klinik). Tanpa WhatsApp API yang siap, `available()` bernilai salah dan booking menunggu konfirmasi staf.
 */
interface BookingCodeSender
{
    public function available(): bool;

    /**
     * @param  string  $phone  nomor internasional tanpa plus
     * @param  string  $template  nama templat WhatsApp yang disetujui
     *
     * @throws BookingCodeNotSent bila pengiriman gagal; pesannya sudah bersih dari data pasien
     */
    public function send(string $phone, string $code, string $template): void;
}
