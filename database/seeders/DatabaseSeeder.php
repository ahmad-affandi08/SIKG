<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Hanya data master dan data sintetis (tanpa data pasien nyata, M12).
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
    }
}
