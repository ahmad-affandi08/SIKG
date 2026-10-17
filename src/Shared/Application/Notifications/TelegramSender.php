<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Pengirim pesan lewat bot Telegram (HTTPS ke api.telegram.org, tanpa pustaka tambahan). Token bot berasal dari
 * lingkungan (M13), bukan basis data. Gagal kirim tidak melempar: hasilnya false dan log tidak memuat token atau isi pesan.
 */
interface TelegramSender
{
    public function configured(): bool;

    /** Nama pengguna bot tanpa tanda @ (untuk tautan t.me), atau null bila belum diatur. */
    public function botUsername(): ?string;

    public function send(string $chatId, string $text): bool;
}
