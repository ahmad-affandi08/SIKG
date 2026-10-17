<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

/**
 * Status klaim ke penjamin: draft, diajukan, disetujui (sebagian atau penuh), ditolak, lunas, atau dibatalkan (draft saja).
 */
enum ClaimStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Paid => 'Lunas',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Klaim yang masih dihitung terhadap plafon polis dan batas tagihan. */
    public function counts(): bool
    {
        return $this !== self::Rejected && $this !== self::Cancelled;
    }
}
