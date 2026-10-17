<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

final readonly class PaymentMethodSnapshot
{
    public function __construct(public string $id, public string $name, public string $type, public bool $active) {}

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }
}
