<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

final readonly class StockCountSession
{
    public function __construct(
        public string $id,
        public string $scheduledFor,
        public string $status, // open, completed, cancelled
        public string $source, // schedule, manual
        public ?string $note,
    ) {}

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
