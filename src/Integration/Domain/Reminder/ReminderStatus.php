<?php

declare(strict_types=1);

namespace Src\Integration\Domain\Reminder;

enum ReminderStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Skipped => 'Dilewati',
        };
    }
}
