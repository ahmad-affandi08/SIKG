<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Online;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Src\Billing\Application\Online\GatewayCharge;
use Src\Billing\Application\Online\GatewayStatus;
use Src\Billing\Application\Online\PaymentGateway;
use Src\Billing\Application\Online\PaymentGatewayFailure;
use Src\Billing\Domain\Online\IntentStatus;
use Src\Billing\Domain\Online\OnlinePaymentSettings;

/**
 * Klien DOKU (API non-SNAP) untuk QRIS lewat halaman Checkout yang dihosting DOKU, tanpa pustaka tambahan (M1). Tiap permintaan
 * ditandatangani HMAC-SHA256 dengan Secret Key klinik (Client-Id, Request-Id, Request-Timestamp, Request-Target, dan Digest isi untuk POST).
 * Pesan galat hanya memuat status HTTP: tidak pernah Secret Key, nomor, atau nama. Status transaksi selalu ditanyakan lewat Check Status API.
 */
final class DokuGateway implements PaymentGateway
{
    private const CHECKOUT_PATH = '/checkout/v1/payment';

    private const STATUS_PATH = '/orders/v1/status/';

    public function charge(OnlinePaymentSettings $settings, string $orderId, int $amount, int $expiryMinutes): GatewayCharge
    {
        $body = json_encode([
            'order' => ['amount' => $amount, 'invoice_number' => $orderId],
            'payment' => ['payment_due_date' => $expiryMinutes, 'payment_method_types' => ['QRIS']],
        ], JSON_THROW_ON_ERROR);

        $json = $this->call($settings, 'POST', self::CHECKOUT_PATH, $body);
        $payment = is_array($json['response'] ?? null) && is_array($json['response']['payment'] ?? null) ? $json['response']['payment'] : [];
        $url = $payment['url'] ?? null;
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new PaymentGatewayFailure('Penyedia pembayaran tidak mengembalikan halaman pembayaran.', false);
        }
        $token = $payment['token_id'] ?? null;

        return new GatewayCharge(is_string($token) && $token !== '' ? $token : null, $url);
    }

    public function status(OnlinePaymentSettings $settings, string $orderId): GatewayStatus
    {
        // 404 berarti belum ada percobaan bayar untuk invoice itu: masih menunggu.
        $json = $this->call($settings, 'GET', self::STATUS_PATH.rawurlencode($orderId), null, [404]);
        $transaction = is_array($json['transaction'] ?? null) ? $json['transaction'] : [];
        $order = is_array($json['order'] ?? null) ? $json['order'] : [];
        $state = strtoupper((string) ($transaction['status'] ?? 'PENDING'));

        // FAILED, TIMEOUT, dan REDIRECT bukan status akhir: pasien masih dapat mencoba lagi sampai QR kedaluwarsa.
        $mapped = match ($state) {
            'SUCCESS' => IntentStatus::Paid,
            'EXPIRED' => IntentStatus::Expired,
            'REFUNDED' => IntentStatus::Failed,
            default => IntentStatus::Pending,
        };
        $reference = $transaction['original_request_id'] ?? null;

        return new GatewayStatus($mapped, $this->rupiah($order['amount'] ?? null), is_string($reference) ? $reference : null);
    }

    public function cancel(OnlinePaymentSettings $settings, string $orderId): void
    {
        // DOKU Checkout tidak menyediakan pembatalan; halaman pembayaran habis sendiri. Pembayaran yang telanjur masuk ditangani rekonsiliasi.
    }

    public function verifyNotification(OnlinePaymentSettings $settings, array $headers, string $rawBody, string $path): bool
    {
        $clientId = $headers['client-id'] ?? '';
        $requestId = $headers['request-id'] ?? '';
        $timestamp = $headers['request-timestamp'] ?? '';
        $given = $headers['signature'] ?? '';
        if ($settings->secretKey === null || $settings->clientId === null || $clientId === '' || $requestId === '' || $timestamp === '' || $given === '') {
            return false;
        }
        if (! hash_equals($settings->clientId, $clientId)) {
            return false;
        }

        return hash_equals($this->sign($settings->secretKey, $clientId, $requestId, $timestamp, $path, $rawBody), $given);
    }

    /**
     * @param  list<int>  $tolerated  kode HTTP yang diabaikan
     * @return array<string, mixed>
     */
    private function call(OnlinePaymentSettings $settings, string $method, string $path, ?string $body, array $tolerated = []): array
    {
        if ($settings->secretKey === null || $settings->secretKey === '' || $settings->clientId === null || $settings->clientId === '') {
            throw new PaymentGatewayFailure('Client ID atau Secret Key penyedia belum diisi.', false);
        }
        $requestId = (string) Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $headers = [
            'Client-Id' => $settings->clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $this->sign($settings->secretKey, $settings->clientId, $requestId, $timestamp, $path, $body),
        ];
        $url = $this->base($settings).$path;
        try {
            $request = Http::withHeaders($headers)->acceptJson()->timeout(20);
            $response = $body === null ? $request->get($url) : $request->withBody($body, 'application/json')->post($url);
        } catch (ConnectionException) {
            throw new PaymentGatewayFailure('Tidak dapat terhubung ke penyedia pembayaran.', true);
        }

        return $this->parse($response, $tolerated);
    }

    /**
     * Tanda tangan DOKU non-SNAP: HMAC-SHA256 (base64) atas baris `Nama:nilai` dipisah newline tanpa newline akhir; Digest (SHA-256 isi,
     * base64) hanya untuk permintaan dengan isi.
     */
    private function sign(string $secret, string $clientId, string $requestId, string $timestamp, string $path, ?string $body): string
    {
        $lines = ["Client-Id:{$clientId}", "Request-Id:{$requestId}", "Request-Timestamp:{$timestamp}", "Request-Target:{$path}"];
        if ($body !== null) {
            $lines[] = 'Digest:'.base64_encode(hash('sha256', $body, true));
        }

        return 'HMACSHA256='.base64_encode(hash_hmac('sha256', implode("\n", $lines), $secret, true));
    }

    /**
     * @param  list<int>  $tolerated
     * @return array<string, mixed>
     */
    private function parse(Response $response, array $tolerated): array
    {
        $status = $response->status();
        if ($status === 429 || $status >= 500) {
            throw new PaymentGatewayFailure("Penyedia pembayaran sedang tidak tersedia (HTTP {$status}).", true);
        }
        if ($status >= 400 && ! in_array($status, $tolerated, true)) {
            throw new PaymentGatewayFailure("Penyedia pembayaran menolak permintaan (HTTP {$status}).", false);
        }
        $json = $response->json();

        /** @var array<string, mixed> $body */
        $body = is_array($json) ? $json : [];

        return $body;
    }

    private function base(OnlinePaymentSettings $settings): string
    {
        return $settings->environment === OnlinePaymentSettings::ENV_PRODUCTION ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
    }

    /** "20000" atau "20000.00" menjadi 20000 tanpa float (M4). */
    private function rupiah(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) {
            return 0;
        }
        $whole = explode('.', (string) $value)[0];

        return ctype_digit($whole) ? (int) $whole : 0;
    }
}
