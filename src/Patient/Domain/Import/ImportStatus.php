<?php

declare(strict_types=1);

namespace Src\Patient\Domain\Import;

enum ImportStatus: string
{
    case Previewed = 'previewed';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Previewed => 'Menunggu konfirmasi',
            self::Importing => 'Sedang diimpor',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }
}
