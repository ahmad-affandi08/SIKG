<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\WhatsAppChannel;
use Src\Integration\Domain\Reminder\WhatsAppFailure;

/**
 * Mengirim satu pesan lewat saluran klinik: templat bila WhatsApp Resmi (Meta), teks biasa bila penyedia tidak resmi. Pemanggil
 * menyiapkan keduanya (teks dan templat beserta parameter) tanpa perlu mengenal penyedia.
 */
final readonly class WhatsAppMessenger
{
    public function __construct(private WhatsAppGateway $gateway) {}

    /**
     * @param  list<string>  $templateParameters
     *
     * @throws WhatsAppFailure
     */
    public function deliver(WhatsAppChannel $channel, string $phone, string $text, ?string $templateName, array $templateParameters): void
    {
        if (! $channel->ready()) {
            throw new WhatsAppFailure('Saluran WhatsApp belum disiapkan.', false);
        }
        if ($channel->provider->usesTemplates()) {
            if ($templateName === null || trim($templateName) === '') {
                throw new WhatsAppFailure('Nama templat WhatsApp untuk pesan ini belum diisi.', false);
            }
            $this->gateway->sendTemplate($channel, $phone, $templateName, $templateParameters);

            return;
        }

        $this->gateway->sendText($channel, $phone, $text);
    }
}
