<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Tujuan pemberitahuan staf di luar aplikasi: email dan grup Telegram klinik (pengaturan SYS-04). Diimplementasikan
 * konteks Clinic.
 */
interface StaffDestinations
{
    /** @return list<string> */
    public function emails(): array;

    /** @return list<string> */
    public function telegramChatIds(): array;
}
