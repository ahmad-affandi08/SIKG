<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Pengirim email teks polos (SMTP lewat konfigurasi Laravel; berjalan di shared hosting). Gagal kirim tidak melempar:
 * hasilnya false dan penyebab teknis dicatat di log tanpa isi pesan.
 */
interface EmailSender
{
    public function configured(): bool;

    public function send(string $to, string $subject, string $body): bool;
}
