<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Throwable;

/**
 * Fonnte (tidak resmi): POST https://api.fonnte.com/send dengan token akun di header Authorization. Pesan galat tidak memuat nomor,
 * isi pesan, atau token.
 */
final class FonnteGateway
{
    private const ENDPOINT = 'https://api.fonnte.com/send';

    public function sendText(WhatsAppChannel $channel, string $phone, string $text): void
    {
        try {
            $response = Http::withoutRedirecting()->acceptJson()->timeout(20)
                ->withHeaders(['Authorization' => (string) $channel->token])
                ->asForm()->post(self::ENDPOINT, ['target' => $phone, 'message' => $text, 'countryCode' => '62']);
        } catch (ConnectionException) {
            throw new WhatsAppFailure('Tidak dapat terhubung ke Fonnte.', true);
        } catch (Throwable) {
            throw new WhatsAppFailure('Permintaan ke Fonnte tidak dapat dikirim.', false);
        }

        $status = $response->status();
        if ($status === 429 || $status >= 500) {
            throw new WhatsAppFailure("Fonnte sedang tidak tersedia (HTTP {$status}).", true);
        }
        if (! $response->successful() || $response->json('status') !== true) {
            throw new WhatsAppFailure("Fonnte menolak pesan (HTTP {$status}). Periksa token dan status perangkat di fonnte.com.", false);
        }
    }
}
