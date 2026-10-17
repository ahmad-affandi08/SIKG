<?php

declare(strict_types=1);

namespace Src\Orthodontics\Domain;

use DateTimeImmutable;

/**
 * Satu cicilan paket: dijadwalkan, ditagihkan ke tagihan kunjungan, atau dibatalkan (perawatan dihentikan sebelum ditagihkan).
 */
final readonly class InstallmentRecord
{
    public const SCHEDULED = 'scheduled';

    public const INVOICED = 'invoiced';

    public const CANCELLED = 'cancelled';

    public function __construct(
        public string $id,
        public string $caseId,
        public int $seq,
        public string $dueDate,
        public int $amount,
        public string $status,
        public ?string $encounterId,
        public ?string $invoiceId,
        public ?DateTimeImmutable $invoicedAt,
    ) {}
}
