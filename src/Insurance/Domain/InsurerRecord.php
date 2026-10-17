<?php

declare(strict_types=1);

namespace Src\Insurance\Domain;

final readonly class InsurerRecord
{
    public function __construct(public string $id, public string $name, public string $type, public bool $active) {}

    public function typeLabel(): string
    {
        return $this->type === 'corporate' ? 'Perusahaan' : 'Asuransi';
    }
}
