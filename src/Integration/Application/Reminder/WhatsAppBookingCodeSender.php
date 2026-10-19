<?php

declare(strict_types=1);

namespace Src\Integration\Application\Reminder;

use Src\Clinic\Contracts\LetterheadProvider;
use Src\Integration\Domain\Reminder\ReminderSettingsRepository;
use Src\Integration\Domain\Reminder\WhatsAppFailure;
use Src\Scheduling\Contracts\BookingCodeNotSent;
use Src\Scheduling\Contracts\BookingCodeSender;

/**
 * P3-01: kode verifikasi booking online dikirim lewat saluran WhatsApp klinik (pengaturan yang sama dengan pengingat dan pemberitahuan
 * staf): templat bagi WhatsApp Resmi, teks biasa bagi penyedia tidak resmi. Tanpa saluran otomatis, tidak ada pengiriman dan booking
 * menunggu konfirmasi staf. Kode tidak ditulis ke log.
 */
final readonly class WhatsAppBookingCodeSender implements BookingCodeSender
{
    public function __construct(
        private ReminderSettingsRepository $settings,
        private WhatsAppMessenger $messenger,
        private LetterheadProvider $letterhead,
    ) {}

    public function available(): bool
    {
        return $this->settings->get()->channel->ready();
    }

    public function send(string $phone, string $code, string $template): void
    {
        $channel = $this->settings->get()->channel;
        if (! $channel->ready()) {
            throw new BookingCodeNotSent('Saluran WhatsApp belum disiapkan.');
        }
        $text = "Kode verifikasi booking {$this->letterhead->forDocument()->clinicName}: {$code}. Jangan bagikan kode ini kepada siapa pun.";
        try {
            $this->messenger->deliver($channel, $phone, $text, $template, [$code]);
        } catch (WhatsAppFailure $e) {
            throw new BookingCodeNotSent($e->getMessage(), 0, $e);
        }
    }
}
