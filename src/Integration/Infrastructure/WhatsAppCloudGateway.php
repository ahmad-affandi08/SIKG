<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Src\Integration\Application\Reminder\WhatsAppGateway;
use Src\Integration\Domain\Reminder\ReminderSettings;
use Src\Integration\Domain\Reminder\WhatsAppFailure;

/**
 * Klien WhatsApp Business Cloud API (Meta) untuk pesan templat. Tanpa pustaka tambahan. Pesan galat tidak memuat nomor, nama, atau
 * token: hanya status HTTP dan kode galat penyedia.
 */
final class WhatsAppCloudGateway implements WhatsAppGateway
{
    private const BASE = 'https://graph.facebook.com/v21.0/';

    public function sendTemplate(ReminderSettings $settings, string $phone, string $templateName, array $parameters): void
    {
        try {
            $response = Http::withToken((string) $settings->apiToken)->acceptJson()->asJson()->timeout(20)->post(self::BASE.rawurlencode((string) $settings->apiPhoneNumberId).'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D/', '', $phone),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $settings->apiLanguage],
                    'components' => [['type' => 'body', 'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $parameters)]],
                ],
            ]);
        } catch (ConnectionException) {
            throw new WhatsAppFailure('Tidak dapat terhubung ke WhatsApp Business API.', true);
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
