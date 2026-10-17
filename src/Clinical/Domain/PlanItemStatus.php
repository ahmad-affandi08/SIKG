<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Status langkah rencana perawatan (RM-06): Direncanakan, Berjalan, Selesai.
 */
enum PlanItemStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::InProgress => 'Berjalan',
            self::Done => 'Selesai',
        };
    }
}
