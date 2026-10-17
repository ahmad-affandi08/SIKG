<?php

declare(strict_types=1);

namespace Src\Lab\Domain;

enum LabOrderStatus: string
{
    case Ordered = 'ordered';
    case Sent = 'sent';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Dipesan',
            self::Sent => 'Dikirim ke lab',
            self::Received => 'Diterima dari lab',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
