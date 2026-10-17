<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

use DateTimeImmutable;

final readonly class PrescriptionRecord
{
    /**
     * @param  list<PrescriptionItemRecord>  $items
     */
    public function __construct(
        public string $id,
        public string $encounterId,
        public string $patientId,
        public string $doctorId,
        public DateTimeImmutable $issuedAt,
        public string $status,
        public ?string $voidReason,
        public array $items,
    ) {}

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }
}
