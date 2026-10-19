<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Src\Integration\Application\Reminder\WhatsAppGateway;
use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Throwable;

/**
 * Klien WhatsApp Business Cloud API (Meta) untuk pesan templat. Tanpa pustaka tambahan. Pesan galat tidak memuat nomor, nama, atau
 * token: hanya status HTTP dan kode galat penyedia.
 */
final class WhatsAppCloudGateway implements WhatsAppGateway
{
    private const BASE = 'https://graph.facebook.com/v21.0/';

    public function sendText(WhatsAppChannel $channel, string $phone, string $text): void
    {
        throw new WhatsAppFailure('WhatsApp Resmi hanya mengirim pesan berbasis templat.', false);
    }

    public function sendTemplate(WhatsAppChannel $channel, string $phone, string $templateName, array $parameters): void
    {
        try {
            $response = Http::withToken((string) $channel->token)->acceptJson()->asJson()->timeout(20)->post(self::BASE.rawurlencode((string) $channel->phoneNumberId).'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D/', '', $phone),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $channel->language],
                    'components' => [['type' => 'body', 'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $parameters)]],
                ],
            ]);
        } catch (ConnectionException) {
            throw new WhatsAppFailure('Tidak dapat terhubung ke WhatsApp Business API.', true);
        } catch (Throwable) {
            throw new WhatsAppFailure('Permintaan ke WhatsApp Business API tidak dapat dikirim.', false);
        }

        if ($response->successful()) {
            return;
        }
        $status = $response->status();
        if ($status === 429 || $status >= 500) {
            throw new WhatsAppFailure("WhatsApp Business API sedang tidak tersedia (HTTP {$status}).", true);
        }
        $code = $response->json('error.code');

        throw new WhatsAppFailure("WhatsApp Business API menolak pesan (HTTP {$status}".(is_int($code) ? ", kode {$code}" : '').').', false);
    }
}
