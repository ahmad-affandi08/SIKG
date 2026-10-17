<?php

declare(strict_types=1);

namespace Src\Pharmacy\Domain;

interface ProcedureMaterialRepository
{
    /**
     * @return list<ProcedureMaterial> aktif, untuk satu tindakan
     */
    public function activeFor(string $procedureId): array;

    /**
     * @return list<ProcedureMaterial> semua baris (aktif dan nonaktif) untuk tampilan pengaturan
     */
    public function all(): array;

    /**
     * Menetapkan jumlah bahan untuk pasangan tindakan dan obat; membuat atau mengaktifkan ulang barisnya.
     */
    public function set(string $actorId, string $procedureId, string $medicineId, int $quantity): ProcedureMaterial;

    public function find(string $id): ?ProcedureMaterial;

    public function deactivate(string $actorId, string $id): void;
}
