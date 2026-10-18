<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Application;

use Src\Clinic\Contracts\BranchDirectory;
use Src\IdentityAccess\Domain\InvalidBranchChoice;
use Src\IdentityAccess\Domain\UserDirectory;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;

/**
 * P3-04: pilih cabang aktif. Pengguna yang tidak terikat satu cabang (Pemilik, Admin) dapat bekerja sebagai satu cabang atau melihat
 * semua cabang; pengguna terikat cabang selalu bekerja di cabangnya dan tidak dapat berpindah. Pilihan hanya disimpan di sesi.
 */
final readonly class ActiveBranch
{
    /** Kunci sesi pilihan cabang aktif. */
    public const SESSION_KEY = 'active_branch_id';

    public function __construct(private BranchDirectory $branches, private UserDirectory $users, private AuditLogger $audit) {}

    /**
     * @param  string|null  $branchId  cabang yang dipilih; null berarti semua cabang
     * @return string|null cabang yang disimpan di sesi
     *
     * @throws InvalidBranchChoice
     */
    public function choose(string $actorId, ?string $branchId): ?string
    {
        if ($this->users->findLoginCandidateById($actorId)?->branchId !== null) {
            throw new InvalidBranchChoice('branch.fixed', 'Akun Anda terikat pada satu cabang dan tidak dapat berpindah.');
        }
        if ($branchId !== null && ! $this->isUsable($branchId)) {
            throw new InvalidBranchChoice('branch.not_found', 'Cabang tidak ditemukan atau tidak aktif.');
        }
        $this->audit->record(new AuditEntry($actorId, 'branch.switched', 'branch', $branchId ?? 'all', null, ['branch_id' => $branchId]));

        return $branchId;
    }

    /** Cabang pilihan sesi masih ada dan aktif? Dipakai saat memulihkan pilihan di tiap permintaan. */
    public function isUsable(string $branchId): bool
    {
        foreach ($this->branches->all() as $branch) {
            if ($branch->id === $branchId) {
                return $branch->active;
            }
        }

        return false;
    }
}
