<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Notifications\TelegramInbox;
use Throwable;

final class TelegramBotInbox implements TelegramInbox
{
    public function updates(int $offset): array
    {
        $token = (string) config('sikg.telegram_bot_token');
        if ($token === '') {
            return [];
        }

        try {
            $response = Http::timeout(8)->get('https://api.telegram.org/bot'.$token.'/getUpdates', [
                'offset' => $offset,
                'limit' => 50,
                'timeout' => 0,
                'allowed_updates' => json_encode(['message']),
            ]);
            if (! $response->successful()) {
                Log::warning('Pembacaan pesan Telegram ditolak.', ['status' => $response->status()]);

                return [];
            }

            $list = [];
            foreach ((array) $response->json('result', []) as $update) {
                $message = $update['message'] ?? null;
                $updateId = $update['update_id'] ?? null;
                if (! is_int($updateId)) {
                    continue;
                }
                // Pesan yang tidak relevan tetap dikembalikan dengan teks kosong agar offset maju melewatinya.
                $private = is_array($message) && ($message['chat']['type'] ?? null) === 'private';
                $list[] = [
                    'update_id' => $updateId,
                    'chat_id' => $private ? (string) ($message['chat']['id'] ?? '') : '',
                    'text' => $private ? (string) ($message['text'] ?? '') : '',
                ];
            }

            return $list;
        } catch (Throwable $e) {
            // Pesan galat HTTP dapat memuat URL berisi token; hanya jenis galat yang dicatat.
            Log::warning('Pembacaan pesan Telegram gagal.', ['exception' => $e::class]);

            return [];
        }
    }
}
