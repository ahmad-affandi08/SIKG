<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

enum NoteSection: string
{
    case ExtraOral = 'extra_oral';
    case IntraOral = 'intra_oral';

    public function label(): string
    {
        return match ($this) {
            self::ExtraOral => 'Pemeriksaan ekstra oral',
            self::IntraOral => 'Pemeriksaan intra oral',
        };
    }
}
