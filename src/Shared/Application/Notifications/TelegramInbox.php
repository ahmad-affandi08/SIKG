<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Pembaca pesan masuk bot Telegram lewat polling `getUpdates` (shared hosting tidak punya webhook yang andal, M21). Hanya pesan
 * teks dari obrolan pribadi yang dikembalikan; gagal membaca menghasilkan daftar kosong dan tidak melempar.
 */
interface TelegramInbox
{
    /**
     * @param  int  $offset  update_id berikutnya yang diminta (0 untuk semua yang belum dikonfirmasi)
     * @return list<array{update_id: int, chat_id: string, text: string}>
     */
    public function updates(int $offset): array;
}
