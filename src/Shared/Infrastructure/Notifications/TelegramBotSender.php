<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Shared\Application\Notifications\TelegramSender;
use Throwable;

final class TelegramBotSender implements TelegramSender
{
    private const API = 'https://api.telegram.org/bot';

    public function configured(): bool
    {
        return $this->token() !== '';
    }

    public function send(string $chatId, string $text): bool
    {
        if (! $this->configured() || $chatId === '') {
            return false;
        }

        try {
            $response = Http::timeout(8)->asForm()->post(self::API.$this->token().'/sendMessage', [
                'chat_id' => $chatId,
                'text' => mb_substr($text, 0, 3900),
                'disable_web_page_preview' => 'true',
            ]);

            if (! $response->successful()) {
                Log::warning('Pengiriman Telegram ditolak.', ['status' => $response->status()]);
            }

            return $response->successful();
        } catch (Throwable $e) {
            // Pesan galat HTTP dapat memuat URL berisi token; hanya jenis galat yang dicatat.
            Log::warning('Pengiriman Telegram gagal.', ['exception' => $e::class]);

            return false;
        }
    }

    private function token(): string
    {
        return (string) config('sikg.telegram_bot_token');
    }
}
