<?php

declare(strict_types=1);

use Src\Clinic\Infrastructure\Eloquent\BranchModel;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Application\AuditEntry;
use Src\Shared\Application\AuditLogger;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

it('AUTH-02 pengguna klinik A tidak dapat membaca cabang klinik B (IDOR)', function () {
    $a = makeClinic('Klinik A');
    $b = makeClinic('Klinik B');
    $cabangA = makeBranch($a);
    $cabangB = makeBranch($b);

    tenant()->set($a->id);

    expect(BranchModel::query()->pluck('id')->all())->toBe([$cabangA->id])
        ->and(BranchModel::query()->find($cabangB->id))->toBeNull()
        ->and(BranchModel::query()->where('id', $cabangB->id)->exists())->toBeFalse();
});

it('AUTH-02 pengguna dan klinik lain tidak terlihat', function () {
    $a = makeClinic('Klinik A');
    $b = makeClinic('Klinik B');
    $userA = makeUser(Role::Admin, $a);
    $userB = makeUser(Role::Admin, $b);

    tenant()->set($a->id);

    expect(User::query()->pluck('id')->all())->toBe([$userA->id])
        ->and(User::query()->find($userB->id))->toBeNull()
        ->and(ClinicModel::query()->pluck('id')->all())->toBe([$a->id]);
});

it('M3 tanpa konteks klinik semua query bertenant kosong (gagal tertutup)', function () {
    $a = makeClinic();
    makeBranch($a);
    makeUser(Role::Admin, $a);

    tenant()->clear();

    expect(BranchModel::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0)
        ->and(ClinicModel::query()->count())->toBe(0);
});

it('M3 data baru otomatis memakai klinik aktif dan menolak dibuat tanpa konteks', function () {
    $a = makeClinic();
    tenant()->set($a->id);

    $cabang = new BranchModel;
    $cabang->forceFill(['name' => 'Cabang Baru'])->save();

    expect($cabang->clinic_id)->toBe($a->id);

    tenant()->clear();
    (new BranchModel)->forceFill(['name' => 'Tanpa Konteks'])->save();
})->throws(LogicException::class);

it('M3 pekerjaan sistem berjalan sebagai klinik tertentu lalu konteks dipulihkan', function () {
    $a = makeClinic();
    $b = makeClinic();
    makeBranch($a);
    tenant()->set($b->id);

    $count = tenant()->runAs($a->id, null, fn () => BranchModel::query()->count());

    expect($count)->toBe(1)
        ->and(tenant()->clinicId())->toBe($b->id)
        ->and(BranchModel::query()->count())->toBe(0);
});

it('M3 pengguna terikat cabang hanya melihat jejak audit cabangnya', function () {
    $klinik = makeClinic();
    $cabang1 = makeBranch($klinik, 'Cabang 1');
    $cabang2 = makeBranch($klinik, 'Cabang 2');
    $logger = app(AuditLogger::class);

    tenant()->set($klinik->id, $cabang1->id);
    $logger->record(new AuditEntry(null, 'patient.updated', 'patient', '01ARZ3NDEKTSV4RRFFQ69G5FAV'));
    tenant()->set($klinik->id, $cabang2->id);
    $logger->record(new AuditEntry(null, 'patient.updated', 'patient', '01ARZ3NDEKTSV4RRFFQ69G5FAW'));

    tenant()->set($klinik->id, $cabang1->id);
    expect(AuditLogModel::query()->count())->toBe(1);

    tenant()->set($klinik->id, null);
    expect(AuditLogModel::query()->count())->toBe(2);
});

it('AUTH-02 halaman hanya menampilkan klinik milik pengguna lewat props bersama', function () {
    $a = makeClinic('Klinik A');
    makeClinic('Klinik B');
    $user = makeUser(Role::Admin, $a);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('clinic.name', 'Klinik A'));
});
