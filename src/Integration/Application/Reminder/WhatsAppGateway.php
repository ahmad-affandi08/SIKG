<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;

/**
 * Pintu ke penyedia WhatsApp pilihan klinik (opsional, berbiaya sesuai penyedia). WhatsApp Resmi (Meta) mengirim pesan berbasis
 * templat yang sudah disetujui; penyedia tidak resmi mengirim teks biasa.
 */
interface WhatsAppGateway
{
    /**
     * Teks biasa (penyedia tidak resmi).
     *
     * @param  string  $phone  nomor internasional tanpa plus (628...)
     *
     * @throws WhatsAppFailure galat sementara atau permanen; pesannya sudah bersih dari data pasien
     */
    public function sendText(WhatsAppChannel $channel, string $phone, string $text): void;

    /**
     * Pesan berbasis templat (WhatsApp Resmi/Meta).
     *
     * @param  list<string>  $parameters  isi {{1}}, {{2}}, ... templat
     *
     * @throws WhatsAppFailure galat sementara atau permanen; pesannya sudah bersih dari data pasien
     */
    public function sendTemplate(WhatsAppChannel $channel, string $phone, string $templateName, array $parameters): void;
}
