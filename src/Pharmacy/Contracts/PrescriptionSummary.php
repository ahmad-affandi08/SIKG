<?php

declare(strict_types=1);

namespace Src\Pharmacy\Contracts;

final readonly class PrescriptionSummary
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(public string $id, public string $encounterId, public string $status, public ?string $voidReason, public string $issuedAt, public array $items) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'void_reason' => $this->voidReason, 'issued_at' => $this->issuedAt, 'items' => $this->items];
    }
}
