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

    /** @var array<string, ImportableMaster> */
    private array $importOnly = [];

    public function register(MasterEntity $entity): void
    {
        $this->entities[$entity->key()] = $entity;
    }

    /**
     * Entitas yang hanya dapat diimpor (tanpa halaman daftar dan formulir), mis. stok awal obat.
     */
    public function registerImportOnly(ImportableMaster $entity): void
    {
        $this->importOnly[$entity->key()] = $entity;
    }

    /**
     * Semua entitas yang dapat diimpor: master terdaftar yang menyatakan pengenal alaminya, ditambah yang hanya-impor.
     *
     * @return list<ImportableMaster>
     */
    public function importable(): array
    {
        $list = array_values($this->importOnly);
        foreach ($this->entities as $entity) {
            if ($entity instanceof ImportableMaster) {
                $list[] = $entity;
            }
        }

        return $list;
    }

    public function importableByKey(string $key): ?ImportableMaster
    {
        foreach ($this->importable() as $entity) {
            if ($entity->key() === $key) {
                return $entity;
            }
        }

        return null;
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
