<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Peran dan izin, lalu data awal untuk setiap klinik yang sudah ada (tanpa data pasien nyata, M12).
     */
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, StarterDataSeeder::class]);
    }
}
