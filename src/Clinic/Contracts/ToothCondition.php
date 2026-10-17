<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

final readonly class ToothCondition
{
    /**
     * @param  'surface'|'tooth'|'both'  $scope
     */
    public function __construct(public string $code, public string $name, public string $color, public string $scope, public bool $active) {}

    /**
     * @return array{code: string, name: string, color: string, scope: string, active: bool}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'color' => $this->color, 'scope' => $this->scope, 'active' => $this->active];
    }
}
