<?php

declare(strict_types=1);

namespace Src\Clinical\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * Tindakan dicatat pada kunjungan. Billing mendengarkan untuk menambah item draft tagihan dengan salinan tarif (KSR-01,
 * MST-08), dan Pharmacy untuk memakai bahan habis pakai tindakan (P2-05). Hanya ID, kode, jumlah, dan tarif; tanpa PHI.
 */
final readonly class ProcedurePerformed implements DomainEvent
{
    public function __construct(
        public string $encounterId,
        public string $encounterProcedureId,
        public string $patientId,
        public string $procedureCode,
        public string $procedureName,
        public int $quantity,
        public int $unitPriceAmount,
        private DateTimeImmutable $at,
        public ?string $procedureId = null,
        public ?string $performedBy = null,
    ) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'clinical.procedure_performed';
    }
}
