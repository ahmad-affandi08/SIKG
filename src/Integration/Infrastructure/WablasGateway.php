<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Throwable;

/**
 * Wablas (tidak resmi): POST {server}/api/send-message dengan token (atau token.kunci-rahasia) di header Authorization. Alamat
 * server berbeda per akun, jadi diisi klinik dan dijaga EndpointGuard.
 */
final readonly class WablasGateway
{
    public function __construct(private PublicHttp $public) {}

    public function sendText(WhatsAppChannel $channel, string $phone, string $text): void
    {
        [$http, $base] = $this->public->client((string) $channel->baseUrl, 'Wablas');

        try {
            $response = $http->withHeaders(['Authorization' => (string) $channel->token])->asForm()->post($base.'/api/send-message', ['phone' => $phone, 'message' => $text]);
        } catch (ConnectionException) {
            throw new WhatsAppFailure('Tidak dapat terhubung ke server Wablas.', true);
        } catch (Throwable) {
            throw new WhatsAppFailure('Permintaan ke server Wablas tidak dapat dikirim.', false);
        }

        $status = $response->status();
        if ($status === 429 || $status >= 500) {
            throw new WhatsAppFailure("Server Wablas sedang tidak tersedia (HTTP {$status}).", true);
        }
        if (! $response->successful() || $response->json('status') !== true) {
            throw new WhatsAppFailure("Wablas menolak pesan (HTTP {$status}). Periksa token dan status perangkat di Wablas.", false);
        }
    }
}
