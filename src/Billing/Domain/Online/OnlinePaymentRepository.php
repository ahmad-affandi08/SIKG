<?php

declare(strict_types=1);

namespace Src\Billing\Domain\Online;

use DateTimeImmutable;

interface OnlinePaymentRepository
{
    /** Pengaturan klinik; token webhook dibuat sekali saat pertama dibutuhkan. */
    public function settings(): OnlinePaymentSettings;

    /** `$secretKey` null berarti kunci lama dipertahankan. */
    public function saveSettings(string $actorId, bool $enabled, string $environment, ?string $clientId, ?string $secretKey, ?string $methodId, int $expiryMinutes): void;

    /**
     * Klinik pemilik token webhook, di luar cakupan klinik.
     *
     * @return string|null clinic_id
     */
    public function clinicForWebhookToken(string $token): ?string;

    public function add(PaymentIntent $intent, string $actorId): void;

    public function find(string $id): ?PaymentIntent;

    public function findByOrderId(string $orderId): ?PaymentIntent;

    public function lockForUpdate(string $id): ?PaymentIntent;

    public function activeForInvoice(string $invoiceId): ?PaymentIntent;

    /** Menyimpan hasil pemanggilan penyedia (tautan halaman pembayaran dan referensi). */
    public function attachCharge(string $id, ?string $paymentUrl, ?string $gatewayRef, DateTimeImmutable $expiresAt): void;

    public function transition(string $id, IntentStatus $status, ?string $note, ?DateTimeImmutable $paidAt, ?string $paymentId): void;

    public function touchChecked(string $id, DateTimeImmutable $at): void;

    /**
     * @return list<PaymentIntent> niat yang masih menunggu (untuk rekonsiliasi), terlama dulu
     */
    public function pending(int $limit): array;

    /**
     * @return list<array<string, mixed>> daftar rekonsiliasi dengan nomor tagihan
     */
    public function listForDesk(int $limit): array;

    /**
     * @return list<PaymentIntent> semua niat sebuah tagihan, terbaru dulu
     */
    public function forInvoice(string $invoiceId): array;
}
