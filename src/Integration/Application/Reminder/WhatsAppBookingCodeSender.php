<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Src\Scheduling\Contracts\BookingCodeNotSent;
use Src\Scheduling\Contracts\BookingCodeSender;

/**
 * P3-01: kode verifikasi booking online dikirim lewat WhatsApp Business API milik klinik (pengaturan yang sama dengan pengingat P2-03,
 * mode `api`). Tanpa kredensial API, tidak ada pengiriman dan booking menunggu konfirmasi staf. Kode tidak ditulis ke log.
 */
final readonly class WhatsAppBookingCodeSender implements BookingCodeSender
{
    public function __construct(private ReminderSettingsRepository $settings, private WhatsAppGateway $gateway) {}

    public function available(): bool
    {
        $settings = $this->settings->get();

        return $settings->isApi() && $settings->apiReady();
    }

    public function send(string $phone, string $code, string $template): void
    {
        $settings = $this->settings->get();
        if (! $settings->isApi() || ! $settings->apiReady()) {
            throw new BookingCodeNotSent('WhatsApp Business API belum disiapkan.');
        }
        try {
            $this->gateway->sendTemplate($settings, $phone, $template, [$code]);
        } catch (WhatsAppFailure $e) {
            throw new BookingCodeNotSent($e->getMessage(), 0, $e);
        }
    }
}
