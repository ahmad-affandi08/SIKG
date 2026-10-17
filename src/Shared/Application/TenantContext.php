<?php

declare(strict_types=1);

namespace Src\Shared\Application;

/**
 * Klinik dan cabang aktif untuk permintaan berjalan (M3). Diisi dari pengguna yang masuk oleh middleware.
 * Tanpa konteks, semua query bertenant mengembalikan kosong (gagal tertutup), bukan data semua klinik.
 */
final class TenantContext
{
    private ?string $clinicId = null;

    private ?string $branchId = null;

    public function set(string $clinicId, ?string $branchId = null): void
    {
        $this->clinicId = $clinicId;
        $this->branchId = $branchId;
    }

    public function clear(): void
    {
        $this->clinicId = null;
        $this->branchId = null;
    }

    public function isSet(): bool
    {
        return $this->clinicId !== null;
    }

    public function clinicId(): ?string
    {
        return $this->clinicId;
    }

    /**
     * Cabang aktif; kosong berarti pengguna melihat semua cabang di kliniknya.
     */
    public function branchId(): ?string
    {
        return $this->branchId;
    }

    /**
     * Menjalankan pekerjaan sistem (job, seeder, perintah) sebagai klinik tertentu, lalu memulihkan konteks.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(string $clinicId, ?string $branchId, callable $callback): mixed
    {
        $previous = [$this->clinicId, $this->branchId];
        $this->set($clinicId, $branchId);

        try {
            return $callback();
        } finally {
            [$this->clinicId, $this->branchId] = $previous;
        }
    }
}
