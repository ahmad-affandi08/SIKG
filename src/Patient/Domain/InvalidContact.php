<?php

declare(strict_types=1);

namespace Src\Patient\Domain;

use Src\Shared\Domain\DomainException;

final class InvalidContact extends DomainException
{
    public static function noChannel(): self
    {
        return new self('patient.contact_no_channel', 'Pengingat hanya dapat diaktifkan bila pasien memiliki email atau akun Telegram yang tertaut.');
    }

    public static function notConfigured(): self
    {
        return new self('patient.telegram_not_configured', 'Bot Telegram belum diatur oleh administrator server.');
    }
}
