<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Peringatan untuk staf: terbit dalam aplikasi (SYS-05) dan, hanya saat pertama kali terbit, diteruskan ke nomor WhatsApp staf
 * klinik bila saluran WhatsApp dikonfigurasi. Penerusan tidak pernah menggagalkan pekerjaan pemanggil; kegagalan hanya dicatat
 * oleh pengirim.
 */
final readonly class StaffAlerts
{
    public function __construct(
        private Notifier $notifier,
        private StaffDestinations $destinations,
        private StaffWhatsApp $whatsapp,
    ) {}

    /**
     * @return bool true bila pemberitahuan baru terbit (bukan duplikat)
     */
    public function raise(Notification $notification): bool
    {
        if (! $this->notifier->publish($notification)) {
            return false;
        }

        if ($this->whatsapp->configured()) {
            foreach ($this->destinations->whatsappNumbers() as $number) {
                $this->whatsapp->send($number, $notification->title, $notification->body);
            }
        }

        return true;
    }
}
