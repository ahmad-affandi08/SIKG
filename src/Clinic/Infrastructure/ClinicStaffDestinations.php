<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Shared\Application\Notifications\StaffDestinations;

/**
 * Tujuan pemberitahuan staf dari pengaturan klinik (SYS-04): nomor WhatsApp staf.
 */
final class ClinicStaffDestinations implements StaffDestinations
{
    public function whatsappNumbers(): array
    {
        return ClinicModel::query()->first()?->clinicSettings()->notifyWhatsapp ?? [];
    }
}
