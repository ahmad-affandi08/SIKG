<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Throwable;

/**
 * WAHA / WhatsApp HTTP API (tidak resmi, dipasang klinik sendiri): POST {server}/api/sendText dengan kunci API (bila ada) di header
 * X-Api-Key dan nama sesi (bawaan `default`).
 */
final readonly class WahaGateway
{
    public function __construct(private PublicHttp $public) {}

    public function sendText(WhatsAppChannel $channel, string $phone, string $text): void
    {
        [$http, $base] = $this->public->client((string) $channel->baseUrl, 'WAHA');
        if ($channel->token !== null && $channel->token !== '') {
            $http = $http->withHeaders(['X-Api-Key' => $channel->token]);
        }

        try {
            $response = $http->asJson()->post($base.'/api/sendText', [
                'session' => $channel->session !== null && $channel->session !== '' ? $channel->session : 'default',
                'chatId' => $phone.'@c.us',
                'text' => $text,
            ]);
        } catch (ConnectionException) {
            throw new WhatsAppFailure('Tidak dapat terhubung ke server WAHA.', true);
        } catch (Throwable) {
            throw new WhatsAppFailure('Permintaan ke server WAHA tidak dapat dikirim.', false);
        }

        $status = $response->status();
        if ($status === 429 || $status >= 500) {
            throw new WhatsAppFailure("Server WAHA sedang tidak tersedia (HTTP {$status}).", true);
        }
        if (! $response->successful()) {
            throw new WhatsAppFailure("WAHA menolak pesan (HTTP {$status}). Periksa kunci API dan status sesi.", false);
        }
    }
}
