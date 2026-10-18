<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use Src\Billing\Domain\Online\OnlinePaymentSettings;

/**
 * Pintu ke penyedia pembayaran online (P3-05; DOKU). Pesan galat sudah bersih dari kunci dan data pasien.
 */
interface PaymentGateway
{
    /**
     * Membuat pembayaran QRIS di halaman Checkout penyedia.
     *
     * @throws PaymentGatewayFailure
     */
    public function charge(OnlinePaymentSettings $settings, string $orderId, int $amount, int $expiryMinutes): GatewayCharge;

    /**
     * Status transaksi menurut penyedia (sumber kebenaran untuk rekonsiliasi).
     *
     * @throws PaymentGatewayFailure
     */
    public function status(OnlinePaymentSettings $settings, string $orderId): GatewayStatus;

    /**
     * Membatalkan pembayaran bila penyedia mendukungnya; DOKU Checkout tidak punya pembatalan, jadi tidak melakukan apa pun dan
     * pembayaran yang telanjur masuk ditangani rekonsiliasi.
     *
     * @throws PaymentGatewayFailure
     */
    public function cancel(OnlinePaymentSettings $settings, string $orderId): void;

    /**
     * Notifikasi HTTP sah menurut kunci klinik: tanda tangan header dihitung dari Client-Id, Request-Id, Request-Timestamp, alamat
     * penerima, dan digest isi mentah.
     *
     * @param  array<string, string>  $headers  nama header huruf kecil => nilai
     */
    public function verifyNotification(OnlinePaymentSettings $settings, array $headers, string $rawBody, string $path): bool;
}
