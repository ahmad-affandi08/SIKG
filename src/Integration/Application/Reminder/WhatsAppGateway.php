<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\ReminderSettings;
use Src\Integration\Domain\Reminder\WhatsAppFailure;

/**
 * Pintu ke WhatsApp Business API (opsional, berbiaya sesuai penyedia). Mengirim satu pesan berbasis templat yang sudah disetujui.
 */
interface WhatsAppGateway
{
    /**
     * @param  list<string>  $parameters  isi {{1}}, {{2}}, ... templat
     *
     * @throws WhatsAppFailure galat sementara atau permanen; pesannya sudah bersih dari data pasien
     */
    public function sendTemplate(ReminderSettings $settings, string $phone, string $templateName, array $parameters): void;
}
