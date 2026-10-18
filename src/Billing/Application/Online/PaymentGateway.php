<?php

declare(strict_types=1);

namespace Src\Billing\Application\Online;

use Src\Billing\Domain\Online\OnlinePaymentSettings;

/**
 * Pintu ke penyedia pembayaran online (P3-05; Midtrans). Pesan galat sudah bersih dari kunci dan data pasien.
 */
interface PaymentGateway
{
    /**
     * Membuat QRIS dinamis.
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
     * @throws PaymentGatewayFailure
     */
    public function cancel(OnlinePaymentSettings $settings, string $orderId): void;

    /**
     * Tanda tangan notifikasi webhook sah menurut kunci klinik.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(OnlinePaymentSettings $settings, array $payload): bool;
}
