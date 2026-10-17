<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\IdentityAccess\Domain\Permission;
use Src\IdentityAccess\Domain\Role;

/*
 * Sumber kebenaran: docs/PRD/00-ringkasan-lingkup-persona.md bagian 4 (matriks hak akses default).
 * Daftar di bawah ditulis TERPISAH dari DefaultRolePermissions agar kesalahan salin tidak lolos diam-diam.
 * Setiap peran diuji terhadap SETIAP izin: izin yang tidak tercantum harus ditolak (bawaan aman).
 */
dataset('matriks', [
    'pemilik' => [Role::Pemilik, [
        'patient.view', 'appointment.view', 'encounter.view', 'odontogram.view', 'prescription.view',
        'invoice.view', 'invoice.void', 'payment.view', 'payment.refund', 'stock.view',
        'tariff.view', 'tariff.manage', 'report.view', 'report.financial', 'report.clinical',
        'user.view', 'user.manage', 'role.manage', 'audit.view', 'setting.manage',
    ]],
    'admin' => [Role::Admin, [
        'patient.view', 'patient.create', 'patient.update', 'patient.delete', 'patient.import', 'patient.merge',
        'appointment.view', 'appointment.manage', 'encounter.view', 'odontogram.view', 'prescription.view',
        'invoice.view', 'invoice.void', 'payment.view', 'payment.refund',
        'stock.view', 'stock.manage', 'stock.override', 'tariff.view', 'tariff.manage',
        'report.view', 'report.financial', 'report.clinical', 'record.export', 'record.print',
        'user.view', 'user.manage', 'role.manage', 'audit.view', 'setting.manage',
    ]],
    'resepsionis' => [Role::Resepsionis, [
        'patient.view', 'patient.create', 'patient.update',
        'appointment.view', 'appointment.manage', 'invoice.view', 'tariff.view',
    ]],
    'dokter' => [Role::Dokter, [
        'patient.view', 'appointment.view',
        'encounter.view', 'encounter.create', 'encounter.write', 'encounter.close', 'encounter.addendum',
        'odontogram.view', 'odontogram.write',
        'prescription.view', 'prescription.create', 'prescription.update',
        'invoice.view', 'stock.view', 'tariff.view', 'report.view', 'report.clinical.own',
    ]],
    'perawat' => [Role::Perawat, [
        'patient.view', 'appointment.view', 'encounter.view', 'encounter.create', 'encounter.write',
        'odontogram.view', 'prescription.view', 'stock.view', 'stock.manage', 'tariff.view',
    ]],
    'kasir' => [Role::Kasir, [
        'patient.view', 'appointment.view', 'prescription.view',
        'invoice.view', 'invoice.edit', 'invoice.discount', 'payment.view', 'payment.create', 'cash.close',
        'tariff.view', 'report.view', 'report.financial.own',
    ]],
]);

beforeEach(fn () => seedRoles());

it('AUTH-02 setiap peran hanya memiliki izin sesuai matriks, selebihnya ditolak', function (Role $role, array $allowed) {
    $user = makeUser($role);

    foreach (Permission::cases() as $permission) {
        $expected = in_array($permission->value, $allowed, true);

        expect($user->hasPermissionTo($permission->value))
            ->toBe($expected, "{$role->value} / {$permission->value} seharusnya ".($expected ? 'diizinkan' : 'ditolak'));
    }
})->with('matriks');

it('AUTH-02 izin di matriks semuanya terdaftar sebagai Permission', function (Role $role, array $allowed) {
    $known = array_map(fn (Permission $p) => $p->value, Permission::cases());

    expect(array_diff($allowed, $known))->toBe([]);
})->with('matriks');

it('AUTH-02 pembatalan tagihan hanya Pemilik dan Admin; kasir dan dokter ditolak di server', function () {
    Route::middleware(['web', 'auth', 'can:invoice.void'])->post('/uji/void', fn () => 'ok');

    foreach ([Role::Pemilik, Role::Admin] as $role) {
        $this->actingAs(makeUser($role))->post('/uji/void')->assertOk();
    }
    foreach ([Role::Kasir, Role::Dokter, Role::Resepsionis, Role::Perawat] as $role) {
        $this->actingAs(makeUser($role))->post('/uji/void')->assertForbidden();
    }
});

it('AUTH-02 izin yang tidak terdefinisi ditolak (bawaan aman)', function () {
    Route::middleware(['web', 'auth', 'can:izin.tidak.ada'])->get('/uji/tak-terdefinisi', fn () => 'ok');

    $this->actingAs(makeUser(Role::Pemilik))->get('/uji/tak-terdefinisi')->assertForbidden();
});

it('AUTH-02 seeder aman dijalankan berulang dan menyelaraskan izin peran', function () {
    seedRoles();
    seedRoles();

    expect(Spatie\Permission\Models\Role::query()->count())->toBe(6)
        ->and(Spatie\Permission\Models\Permission::query()->count())->toBe(count(Permission::cases()));
});

it('AUTH-02 izin ikut dibagikan ke antarmuka sebagai kenyamanan menu', function () {
    $this->actingAs(makeUser(Role::Kasir))->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.roles', ['kasir'])
            ->has('auth.permissions', fn ($p) => $p->etc()));
});
