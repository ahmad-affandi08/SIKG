<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Peringatan untuk staf: terbit dalam aplikasi (SYS-05) dan, hanya saat pertama kali terbit, diteruskan ke email dan Telegram
 * klinik bila dikonfigurasi. Penerusan tidak pernah menggagalkan pekerjaan pemanggil; kegagalan hanya dicatat oleh pengirim.
 */
final readonly class StaffAlerts
{
    public function __construct(
        private Notifier $notifier,
        private StaffDestinations $destinations,
        private EmailSender $email,
        private TelegramSender $telegram,
    ) {}

    /**
     * @return bool true bila pemberitahuan baru terbit (bukan duplikat)
     */
    public function raise(Notification $notification): bool
    {
        if (! $this->notifier->publish($notification)) {
            return false;
        }

        $subject = '[SIKG] '.$notification->title;
        if ($this->email->configured()) {
            foreach ($this->destinations->emails() as $to) {
                $this->email->send($to, $subject, $notification->body);
            }
        }
        if ($this->telegram->configured()) {
            foreach ($this->destinations->telegramChatIds() as $chatId) {
                $this->telegram->send($chatId, $notification->title."\n".$notification->body);
            }
        }

        return true;
    }
}
