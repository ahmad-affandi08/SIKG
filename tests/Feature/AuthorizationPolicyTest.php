<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

it('AUTH-02 Pemilik dan Admin melihat dan mengelola pengguna biasa', function (Role $aktor) {
    $klinik = makeClinic();
    $pelaku = makeUser($aktor, $klinik);
    $target = makeUser(Role::Resepsionis, $klinik);

    expect(Gate::forUser($pelaku)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($pelaku)->allows('view', $target))->toBeTrue()
        ->and(Gate::forUser($pelaku)->allows('create', User::class))->toBeTrue()
        ->and(Gate::forUser($pelaku)->allows('update', $target))->toBeTrue()
        ->and(Gate::forUser($pelaku)->allows('delete', $target))->toBeTrue();
})->with([Role::Pemilik, Role::Admin]);

it('AUTH-02 Admin tidak dapat mengubah atau menonaktifkan Pemilik, Pemilik dapat', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $pemilik = makeUser(Role::Pemilik, $klinik);

    expect(Gate::forUser($admin)->allows('view', $pemilik))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('update', $pemilik))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('delete', $pemilik))->toBeTrue()
        ->and(Gate::forUser($pemilik)->allows('update', $admin))->toBeTrue();
});

it('AUTH-02 pengguna tidak dapat menonaktifkan dirinya sendiri', function () {
    $pemilik = makeUser(Role::Pemilik);

    expect(Gate::forUser($pemilik)->denies('delete', $pemilik))->toBeTrue();
});

it('AUTH-02 peran lain tidak punya akses ke pengguna sama sekali (bawaan aman)', function (Role $aktor) {
    $klinik = makeClinic();
    $pelaku = makeUser($aktor, $klinik);
    $target = makeUser(Role::Kasir, $klinik);

    expect(Gate::forUser($pelaku)->denies('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($pelaku)->denies('view', $target))->toBeTrue()
        ->and(Gate::forUser($pelaku)->denies('create', User::class))->toBeTrue()
        ->and(Gate::forUser($pelaku)->denies('update', $target))->toBeTrue()
        ->and(Gate::forUser($pelaku)->denies('delete', $target))->toBeTrue();
})->with([Role::Resepsionis, Role::Dokter, Role::Perawat, Role::Kasir]);

it('AUTH-02 Admin klinik A tidak dapat mengelola pengguna klinik B', function () {
    $adminA = makeUser(Role::Admin, makeClinic('Klinik A'));
    $targetB = makeUser(Role::Resepsionis, makeClinic('Klinik B'));

    expect(Gate::forUser($adminA)->denies('view', $targetB))->toBeTrue()
        ->and(Gate::forUser($adminA)->denies('update', $targetB))->toBeTrue()
        ->and(Gate::forUser($adminA)->denies('delete', $targetB))->toBeTrue();
});

it('AUTH-02 izin yang dicabut dari peran langsung berlaku (peran dapat disesuaikan tanpa mengubah kode)', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Kasir, $klinik);

    expect(Gate::forUser($admin)->allows('update', $target))->toBeTrue();

    Spatie\Permission\Models\Role::findByName('admin', 'web')->revokePermissionTo('user.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Gate::forUser($admin->fresh())->denies('update', $target))->toBeTrue();
});
