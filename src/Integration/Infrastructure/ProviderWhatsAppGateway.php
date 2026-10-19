<?php

declare(strict_types=1);

namespace Src\Integration\Infrastructure;

use Src\Integration\Application\Reminder\WhatsAppGateway;
use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Src\Integration\Domain\Reminder\WhatsAppProvider;

/**
 * Memilih klien penyedia sesuai pengaturan klinik. Penyedia tidak resmi hanya mengirim teks biasa; WhatsApp Resmi hanya templat.
 */
final readonly class ProviderWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private WhatsAppCloudGateway $meta,
        private FonnteGateway $fonnte,
        private WablasGateway $wablas,
        private WahaGateway $waha,
    ) {}

    public function sendText(WhatsAppChannel $channel, string $phone, string $text): void
    {
        match ($channel->provider) {
            WhatsAppProvider::Meta => $this->meta->sendText($channel, $phone, $text),
            WhatsAppProvider::Fonnte => $this->fonnte->sendText($channel, $phone, $text),
            WhatsAppProvider::Wablas => $this->wablas->sendText($channel, $phone, $text),
            WhatsAppProvider::Waha => $this->waha->sendText($channel, $phone, $text),
            WhatsAppProvider::Link => throw new WhatsAppFailure('Saluran WhatsApp manual tidak mengirim otomatis.', false),
        };
    }

    public function sendTemplate(WhatsAppChannel $channel, string $phone, string $templateName, array $parameters): void
    {
        if ($channel->provider !== WhatsAppProvider::Meta) {
            throw new WhatsAppFailure('Penyedia ini mengirim teks biasa, bukan templat.', false);
        }
        $this->meta->sendTemplate($channel, $phone, $templateName, $parameters);
    }
}
