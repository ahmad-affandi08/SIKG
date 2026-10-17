<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

use DateTimeImmutable;

/**
 * Penerimaan uang dari penjamin atas satu klaim. Hanya bertambah (tidak diubah atau dihapus).
 */
final readonly class ClaimReceipt
{
    public function __construct(
        public string $id,
        public string $claimId,
        public int $amount,
        public string $methodId,
        public ?string $referenceNo,
        public string $idempotencyKey,
        public DateTimeImmutable $receivedAt,
        public string $receivedBy,
    ) {}
}
