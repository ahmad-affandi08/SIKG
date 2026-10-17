<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\DefaultRolePermissions;
use Src\IdentityAccess\Domain\Permission;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

function izinBawaan(Role $role): array
{
    $nama = array_map(fn (Permission $p) => $p->value, DefaultRolePermissions::for($role));
    sort($nama);

    return $nama;
}

function izinPeran(string $peran): array
{
    $id = DB::table('roles')->where('name', $peran)->value('id');
    $nama = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')->where('role_id', $id)->pluck('permissions.name')->all();
    sort($nama);

    return $nama;
}

function simpanIzin(string $peran, array $izin)
{
    return test()->put("/settings/roles/{$peran}", ['permissions' => $izin]);
}

it('AUTH-07 Pemilik melihat semua peran dengan jumlah pengguna, izin, bawaan, dan kelompok modul', function () {
    $klinik = makeClinic();
    $pemilik = makeUser(Role::Pemilik, $klinik);
    makeUser(Role::Kasir, $klinik);
    makeUser(Role::Kasir, $klinik);

    $this->actingAs($pemilik)->get('/settings/roles')->assertOk()->assertInertia(function ($p) {
        $p->component('Settings/Roles/Index')->has('roles', 6)->has('modules')
            ->where('roles.0.value', 'pemilik')->where('roles.0.editable', true)->where('roles.0.locked', ['user.view', 'user.manage', 'role.manage'])
            ->where('roles.5.value', 'kasir')->where('roles.5.users', 2)->where('roles.5.customized', false)
            ->where('roles.5.permissions', izinBawaan(Role::Kasir))->where('roles.5.defaults', izinBawaan(Role::Kasir));

        return $p;
    });
});

it('AUTH-07 setiap izin punya kelompok modul dan penjelasan Indonesia', function () {
    foreach (Permission::cases() as $izin) {
        expect($izin->module())->not->toBe('')->and($izin->label())->not->toBe('');
    }
});

it('AUTH-07 Pemilik menambah dan mencabut izin peran; berlaku langsung dan tercatat di audit', function () {
    $klinik = makeClinic();
    $pemilik = makeUser(Role::Pemilik, $klinik);
    $kasir = makeUser(Role::Kasir, $klinik);
    expect($kasir->can('stock.manage'))->toBeFalse()->and($kasir->can('payment.create'))->toBeTrue();

    $baru = array_values(array_diff(izinBawaan(Role::Kasir), ['payment.create']));
    $baru[] = 'stock.manage';
    $this->actingAs($pemilik);
    simpanIzin('kasir', $baru)->assertSessionHasNoErrors()->assertRedirect();

    tenant()->set($klinik->id);
    $kasir = User::query()->findOrFail($kasir->id);
    expect($kasir->can('stock.manage'))->toBeTrue()->and($kasir->can('payment.create'))->toBeFalse()
        ->and(DB::table('roles')->where('name', 'kasir')->value('customized_at'))->not->toBeNull();
    $audit = AuditLogModel::query()->where('action', 'role.permissions_changed')->where('subject_id', 'kasir')->firstOrFail();
    expect($audit->actor_id)->toBe($pemilik->id)->and($audit->after['added'])->toBe(['stock.manage'])->and($audit->after['removed'])->toBe(['payment.create']);
});

it('AUTH-07 peran yang disesuaikan tidak ditimpa seeder; mengembalikan ke bawaan menghapus penyesuaian', function () {
    $pemilik = makeUser(Role::Pemilik);
    $this->actingAs($pemilik);
    simpanIzin('perawat', ['patient.view'])->assertSessionHasNoErrors();

    seedRoles();
    expect(izinPeran('perawat'))->toBe(['patient.view']);

    $this->post('/settings/roles/perawat/reset')->assertSessionHasNoErrors();
    tenant()->set($pemilik->clinic_id);
    expect(izinPeran('perawat'))->toBe(izinBawaan(Role::Perawat))->and(DB::table('roles')->where('name', 'perawat')->value('customized_at'))->toBeNull()
        ->and(AuditLogModel::query()->where('action', 'role.permissions_reset')->where('subject_id', 'perawat')->exists())->toBeTrue();
});

it('AUTH-07 menyimpan izin yang sama dengan bawaan tidak menandai peran disesuaikan dan tidak tercatat', function () {
    $this->actingAs(makeUser(Role::Pemilik));

    simpanIzin('dokter', izinBawaan(Role::Dokter))->assertSessionHasNoErrors();

    expect(DB::table('roles')->where('name', 'dokter')->value('customized_at'))->toBeNull()
        ->and(AuditLogModel::query()->where('action', 'role.permissions_changed')->count())->toBe(0);
});

it('AUTH-07 peran Pemilik tidak pernah kehilangan izin kelola pengguna dan peran', function () {
    $this->actingAs(makeUser(Role::Pemilik));

    simpanIzin('pemilik', ['patient.view'])->assertSessionHasNoErrors();

    expect(izinPeran('pemilik'))->toBe(['patient.view', 'role.manage', 'user.manage', 'user.view']);
    $this->get('/settings/roles')->assertOk();
});

it('AUTH-07 Admin dapat mengubah peran selain Pemilik tetapi tidak peran Pemilik', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin);

    simpanIzin('resepsionis', [...izinBawaan(Role::Resepsionis), 'invoice.view'])->assertSessionHasNoErrors();
    simpanIzin('pemilik', izinBawaan(Role::Pemilik))->assertForbidden();
    $this->post('/settings/roles/pemilik/reset')->assertForbidden();

    $this->get('/settings/roles')->assertInertia(fn ($p) => $p->where('roles.0.editable', false)->where('roles.1.editable', true));
    expect(izinPeran('pemilik'))->toBe(izinBawaan(Role::Pemilik));
});

it('AUTH-07 izin tidak dikenal dan peran tidak dikenal ditolak', function () {
    $this->actingAs(makeUser(Role::Pemilik));

    simpanIzin('kasir', ['patient.view', 'bukan.izin'])->assertSessionHas('error');
    simpanIzin('ngawur', ['patient.view'])->assertNotFound();
    $this->post('/settings/roles/ngawur/reset')->assertNotFound();
    $this->put('/settings/roles/kasir', [])->assertSessionHasErrors('permissions');

    expect(izinPeran('kasir'))->toBe(izinBawaan(Role::Kasir));
});

it('AUTH-07 peran lain tanpa izin role.manage ditolak membuka dan mengubah halaman', function (Role $peran) {
    $user = makeUser($peran);
    $this->actingAs($user);

    $this->get('/settings/roles')->assertForbidden();
    simpanIzin('kasir', ['patient.view'])->assertForbidden();
    $this->post('/settings/roles/kasir/reset')->assertForbidden();
    expect(izinPeran('kasir'))->toBe(izinBawaan(Role::Kasir));
})->with([Role::Resepsionis, Role::Dokter, Role::Perawat, Role::Kasir]);

it('AUTH-07 izin tambahan peran dari Pemilik membuka menu dan route yang dilindunginya', function () {
    $klinik = makeClinic();
    $pemilik = makeUser(Role::Pemilik, $klinik);
    $kasir = makeUser(Role::Kasir, $klinik);
    $this->actingAs($kasir)->get('/audit')->assertForbidden();

    $this->actingAs($pemilik);
    simpanIzin('kasir', [...izinBawaan(Role::Kasir), 'audit.view'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $this->actingAs(User::query()->findOrFail($kasir->id))->get('/audit')->assertOk();
});
