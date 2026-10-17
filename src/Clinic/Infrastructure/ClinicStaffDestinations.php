<?php

declare(strict_types=1);

namespace Src\Clinic\Infrastructure;

use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Shared\Application\Notifications\StaffDestinations;

/**
 * Tujuan pemberitahuan staf dari pengaturan klinik (SYS-04): satu email dan satu grup Telegram.
 */
final class ClinicStaffDestinations implements StaffDestinations
{
    public function emails(): array
    {
        $email = ClinicModel::query()->first()?->clinicSettings()->notifyEmail;

        return $email === null ? [] : [$email];
    }

    public function telegramChatIds(): array
    {
        $chat = ClinicModel::query()->first()?->clinicSettings()->telegramChatId;

        return $chat === null ? [] : [$chat];
    }
}
