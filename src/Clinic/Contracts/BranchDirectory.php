<?php

declare(strict_types=1);

namespace Src\Clinic\Contracts;

/**
 * Kontrak untuk konteks lain yang perlu daftar cabang klinik aktif (antar-konteks hanya lewat Contracts dan ID).
 */
interface BranchDirectory
{
    /**
     * @return list<BranchSummary>
     */
    public function all(): array;

    public function exists(string $branchId): bool;
}
