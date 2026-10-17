<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\Clinic\Infrastructure\Eloquent\BranchModel;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\IdentityAccess\Infrastructure\Seeders\RolePermissionSeeder;
use Src\Shared\Application\TenantContext;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Pembantu tes (data sintetis saja, M12)
|--------------------------------------------------------------------------
*/

function seedRoles(): void
{
    app(RolePermissionSeeder::class)->run();
}

function makeClinic(string $name = 'Klinik Uji'): ClinicModel
{
    $clinic = new ClinicModel;
    $clinic->forceFill(['name' => $name, 'timezone' => 'Asia/Jakarta'])->save();

    return $clinic;
}

function makeBranch(ClinicModel $clinic, string $name = 'Cabang Utama'): BranchModel
{
    $branch = new BranchModel;
    $branch->forceFill(['clinic_id' => $clinic->id, 'name' => $name, 'is_active' => true])->save();

    return $branch;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeUser(Role $role = Role::Admin, ?ClinicModel $clinic = null, ?BranchModel $branch = null, array $attributes = []): User
{
    $clinic ??= makeClinic();
    static $counter = 0;
    $counter++;

    $user = new User;
    $user->forceFill([
        'clinic_id' => $clinic->id,
        'branch_id' => $branch?->id,
        'name' => "Pengguna Uji {$counter}",
        'email' => "uji{$counter}@contoh.test",
        'username' => "uji{$counter}",
        'password' => 'kata-sandi-uji-123',
        'is_active' => true,
        ...$attributes,
    ])->save();

    seedRoles();
    $user->assignRole($role->value);

    return $user;
}

function tenant(): TenantContext
{
    return app(TenantContext::class);
}

/**
 * Data pasien sintetis untuk tes pendaftaran dan impor (NIK fiktif berawalan 9999, M12).
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
function payloadPasien(array $o = []): array
{
    return [
        'name' => 'Budi Santoso',
        'nik' => '9999010101900001',
        'birth_date' => '1990-01-01',
        'sex' => 'male',
        'address' => 'Jl. Contoh No. 1',
        'phone' => '08123456789',
        'occupation' => 'Karyawan',
        'emergency_name' => 'Ani Santoso',
        'emergency_relation' => 'Istri',
        'emergency_phone' => '08129876543',
        'consent' => true,
        ...$o,
    ];
}
