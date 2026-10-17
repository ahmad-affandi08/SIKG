<?php

declare(strict_types=1);

namespace Src\Clinical\Domain\Events;

use DateTimeImmutable;
use Src\Shared\Domain\DomainEvent;

/**
 * Tindakan dibatalkan dari kunjungan yang masih berjalan; item draft tagihannya ikut dilepas.
 */
final readonly class ProcedureRetracted implements DomainEvent
{
    public function __construct(public string $encounterId, public string $encounterProcedureId, private DateTimeImmutable $at) {}

    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    public static function eventName(): string
    {
        return 'clinical.procedure_retracted';
    }
}
