<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Infrastructure\Setup;

use Illuminate\Support\Facades\DB;
use Src\Shared\Application\Setup\SetupStep;
use Src\Shared\Application\Setup\SetupStepProvider;

/**
 * MST-10: langkah Penyiapan awal yang datanya dimiliki konteks IdentityAccess (pengguna).
 */
final class UserSetupSteps implements SetupStepProvider
{
    public function steps(string $clinicId): array
    {
        $users = (int) DB::table('users')->where('clinic_id', $clinicId)->whereNull('deleted_at')->where('is_active', true)->count();

        return [
            new SetupStep(80, 'users', 'Pengguna',
                'Tambahkan resepsionis, dokter, perawat, dan kasir dengan peran masing-masing.', $users > 1, false,
                "{$users} pengguna aktif.", '/settings/users', 'Tambah pengguna'),
        ];
    }
}
