<?php

declare(strict_types=1);

namespace Src\Reporting\Domain;

/**
 * Status berkas laporan yang dibuat lewat antrean (SYS-07): menunggu, berjalan, selesai, atau gagal.
 */
enum JobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu',
            self::Running => 'Berjalan',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
