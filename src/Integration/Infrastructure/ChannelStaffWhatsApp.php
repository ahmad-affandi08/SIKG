<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Illuminate\Support\Facades\Log;
use Src\Integration\Application\Reminder\WhatsAppMessenger;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Src\Shared\Application\Notifications\StaffWhatsApp;
use Src\Shared\Domain\Brand;

/**
 * Pemberitahuan staf lewat saluran WhatsApp klinik: templat dua parameter (judul, isi) bagi WhatsApp Resmi, teks biasa bagi
 * penyedia tidak resmi. Tanpa saluran otomatis yang lengkap, tidak ada pengiriman. Nomor, token, dan isi tidak ditulis ke log.
 */
final readonly class ChannelStaffWhatsApp implements StaffWhatsApp
{
    public function __construct(private ReminderSettingsRepository $settings, private WhatsAppMessenger $messenger) {}

    public function configured(): bool
    {
        $channel = $this->settings->get()->channel;

        return $channel->ready() && (! $channel->provider->usesTemplates() || $channel->staffTemplate !== null);
    }

    public function send(string $phone, string $title, string $body): bool
    {
        $channel = $this->settings->get()->channel;
        if (! $this->configured()) {
            return false;
        }

        try {
            $this->messenger->deliver($channel, $phone, '['.Brand::NAME."] {$title}\n{$body}", $channel->staffTemplate, [$title, $body]);

            return true;
        } catch (WhatsAppFailure $e) {
            Log::warning('Pemberitahuan staf lewat WhatsApp gagal.', ['reason' => $e->getMessage()]);

            return false;
        }
    }
}
