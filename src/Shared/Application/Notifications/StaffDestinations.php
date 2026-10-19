<?php

declare(strict_types=1);

namespace Src\Shared\Application\Notifications;

/**
 * Tujuan pemberitahuan staf di luar aplikasi: nomor WhatsApp staf klinik (pengaturan SYS-04). Diimplementasikan konteks Clinic.
 */
interface StaffDestinations
{
    /** @return list<string> nomor internasional tanpa plus (628...) */
    public function whatsappNumbers(): array;
}
