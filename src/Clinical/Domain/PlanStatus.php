<?php

declare(strict_types=1);

namespace Src\Clinical\Domain;

/**
 * Status rencana perawatan. Diturunkan dari status langkahnya, tidak pernah diisi manual (seperti status tagihan, M7);
 * satu-satunya keputusan manual adalah membatalkan rencana.
 */
enum PlanStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::InProgress => 'Berjalan',
            self::Done => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Rencana tanpa langkah atau semua langkah direncanakan: Direncanakan. Semua selesai: Selesai. Selain itu: Berjalan.
     *
     * @param  list<PlanItemStatus>  $items
     */
    public static function fromItems(array $items): self
    {
        if ($items === []) {
            return self::Planned;
        }
        $done = 0;
        $planned = 0;
        $total = 0;
        foreach ($items as $status) {
            $total++;
            $done += $status === PlanItemStatus::Done ? 1 : 0;
            $planned += $status === PlanItemStatus::Planned ? 1 : 0;
        }

        return match (true) {
            $done === $total => self::Done,
            $planned === $total => self::Planned,
            default => self::InProgress,
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Planned || $this === self::InProgress;
    }
}
