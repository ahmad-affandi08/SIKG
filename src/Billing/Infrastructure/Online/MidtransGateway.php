<?php

declare(strict_types=1);

namespace Src\Billing\Infrastructure\Online;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Src\Billing\Application\Online\GatewayCharge;
use Src\Billing\Application\Online\GatewayStatus;
use Src\Billing\Application\Online\PaymentGateway;
use Src\Billing\Application\Online\PaymentGatewayFailure;
use Src\Billing\Domain\Online\IntentStatus;
use Src\Billing\Domain\Online\OnlinePaymentSettings;

/**
 * Klien Midtrans Core API untuk QRIS dinamis, tanpa pustaka tambahan (M1). Pesan galat hanya memuat status HTTP dan kode penyedia:
 * tidak pernah server key, nomor, atau nama. Gambar QR diambil dari tautan resmi penyedia lalu dikirim sebagai data URI agar
 * halaman tidak memuat gambar dari asal luar (CSP).
 */
final class MidtransGateway implements PaymentGateway
{
    public function charge(OnlinePaymentSettings $settings, string $orderId, int $amount, int $expiryMinutes): GatewayCharge
    {
        $body = $this->call($settings, 'POST', '/v2/charge', [
            'payment_type' => 'qris',
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'qris' => ['acquirer' => 'gopay'],
            'custom_expiry' => ['expiry_duration' => $expiryMinutes, 'unit' => 'minute'],
        ]);

        $image = null;
        foreach ((array) ($body['actions'] ?? []) as $action) {
            if (is_array($action) && ($action['name'] ?? null) === 'generate-qr-code' && is_string($action['url'] ?? null)) {
                $image = $this->fetchImage($settings, $action['url']);
            }
        }

        return new GatewayCharge($this->text($body['transaction_id'] ?? null), $this->text($body['qr_string'] ?? null), $image);
    }

    public function status(OnlinePaymentSettings $settings, string $orderId): GatewayStatus
    {
        $body = $this->call($settings, 'GET', '/v2/'.rawurlencode($orderId).'/status');
        $state = (string) ($body['transaction_status'] ?? '');
        $fraud = (string) ($body['fraud_status'] ?? 'accept');

        $mapped = match (true) {
            in_array($state, ['settlement', 'capture'], true) && $fraud === 'accept' => IntentStatus::Paid,
            $state === 'pending' => IntentStatus::Pending,
            $state === 'expire' => IntentStatus::Expired,
            $state === 'cancel' => IntentStatus::Cancelled,
            default => IntentStatus::Failed,
        };

        return new GatewayStatus($mapped, $this->rupiah($body['gross_amount'] ?? null), $this->text($body['transaction_id'] ?? null));
    }

    public function cancel(OnlinePaymentSettings $settings, string $orderId): void
    {
        $this->call($settings, 'POST', '/v2/'.rawurlencode($orderId).'/cancel', [], [404, 412]);
    }

    public function verifySignature(OnlinePaymentSettings $settings, array $payload): bool
    {
        $given = $payload['signature_key'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $gross = $payload['gross_amount'] ?? null;
        if (! is_string($given) || ! is_string($orderId) || ! is_string($statusCode) || ! is_string($gross) || $settings->serverKey === null) {
            return false;
        }

        return hash_equals(hash('sha512', $orderId.$statusCode.$gross.$settings->serverKey), strtolower($given));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<int>  $tolerated  kode HTTP penyedia yang diabaikan (mis. transaksi sudah tidak ada saat dibatalkan)
     * @return array<string, mixed>
     */
    private function call(OnlinePaymentSettings $settings, string $method, string $path, array $payload = [], array $tolerated = []): array
    {
        if ($settings->serverKey === null || $settings->serverKey === '') {
            throw new PaymentGatewayFailure('Server key penyedia belum diisi.', false);
        }
        try {
            $request = $this->client($settings);
            $response = $method === 'GET' ? $request->get($this->base($settings).$path) : $request->post($this->base($settings).$path, $payload);
        } catch (ConnectionException) {
            throw new PaymentGatewayFailure('Tidak dapat terhubung ke penyedia pembayaran.', true);
        }

        return $this->parse($response, $tolerated);
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
        $json = $response->json();
        $body = is_array($json) ? $json : [];
        // Midtrans sering membalas HTTP 200 dengan `status_code` galat di badan.
        $code = (int) ($body['status_code'] ?? $status);
        if (($status >= 400 || $code >= 400) && ! in_array($code, $tolerated, true) && ! in_array($status, $tolerated, true)) {
            throw new PaymentGatewayFailure("Penyedia pembayaran menolak permintaan (HTTP {$status}, kode {$code}).", false);
        }

        /** @var array<string, mixed> $body */
        return $body;
    }

    private function fetchImage(OnlinePaymentSettings $settings, string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || ! str_ends_with($host, 'midtrans.com')) {
            return null;
        }
        try {
            $response = $this->client($settings)->get($url);
        } catch (ConnectionException) {
            return null;
        }
        if (! $response->successful() || $response->body() === '') {
            return null;
        }
        $type = explode(';', (string) $response->header('Content-Type'))[0];

        return 'data:'.($type !== '' ? $type : 'image/png').';base64,'.base64_encode($response->body());
    }

    private function client(OnlinePaymentSettings $settings): PendingRequest
    {
        return Http::withBasicAuth((string) $settings->serverKey, '')->acceptJson()->asJson()->timeout(20);
    }

    private function base(OnlinePaymentSettings $settings): string
    {
        return $settings->environment === OnlinePaymentSettings::ENV_PRODUCTION ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** "10000.00" menjadi 10000 tanpa float (M4). */
    private function rupiah(mixed $value): int
    {
        if (! is_string($value) && ! is_int($value)) {
            return 0;
        }
        $whole = explode('.', (string) $value)[0];

        return ctype_digit($whole) ? (int) $whole : 0;
    }
}
