<?php

declare(strict_types=1);

namespace Src\Shared\Application\Master;

use InvalidArgumentException;

/**
 * Daftar entitas master yang terdaftar dari tiap konteks (ServiceProvider memanggil `register`).
 */
final class MasterEntityRegistry
{
    /** @var array<string, MasterEntity> */
    private array $entities = [];

    public function register(MasterEntity $entity): void
    {
        $this->entities[$entity->key()] = $entity;
    }

    public function has(string $key): bool
    {
        return isset($this->entities[$key]);
    }

    public function get(string $key): MasterEntity
    {
        return $this->entities[$key] ?? throw new InvalidArgumentException("Master data tidak dikenal: {$key}");
    }

    /**
     * @return list<MasterEntity>
     */
    public function all(): array
    {
        return array_values($this->entities);
    }
}
