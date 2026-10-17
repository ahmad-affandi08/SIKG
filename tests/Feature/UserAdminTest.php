<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

function payloadPengguna(array $override = []): array
{
    return [
        'name' => 'Sari Wulandari',
        'email' => 'sari@contoh.test',
        'username' => 'sari.w',
        'role' => 'resepsionis',
        'branch_id' => '',
        ...$override,
    ];
}

it('AUTH-03 Admin melihat daftar pengguna kliniknya saja', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    makeUser(Role::Kasir, $klinik);
    makeUser(Role::Kasir, makeClinic('Klinik Lain'));

    $this->actingAs($admin)->get('/settings/users')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Users/Index')->has('users', 2)->where('canCreate', true));
});

it('AUTH-03 membuat pengguna dengan peran, kata sandi sementara tampil sekali dan hanya hash yang tersimpan', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $response = $this->actingAs($admin)->post('/settings/users', payloadPengguna());

    $response->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Users/Credential')
        ->where('username', 'sari.w')
        ->has('temporaryPassword'));
    $password = $response->inertiaProps('temporaryPassword');

    tenant()->set($klinik->id);
    $baru = User::query()->where('username', 'sari.w')->firstOrFail();
    expect($baru->hasRole('resepsionis'))->toBeTrue()
        ->and($baru->is_active)->toBeTrue()
        ->and($baru->clinic_id)->toBe($klinik->id)
        ->and(strlen($password))->toBeGreaterThanOrEqual(10)
        ->and(Hash::check($password, $baru->password))->toBeTrue()
        ->and(DB::table('users')->where('id', $baru->id)->value('password'))->not->toBe($password);

    // kata sandi tidak masuk audit maupun sesi
    $audit = AuditLogModel::query()->where('action', 'user.created')->firstOrFail();
    expect(json_encode($audit->after))->not->toContain($password)
        ->and(json_encode(session()->all()))->not->toContain($password);
});

it('AUTH-03 pengguna baru dapat masuk dengan kata sandi sementara', function () {
    $admin = makeUser(Role::Admin);
    $password = $this->actingAs($admin)->post('/settings/users', payloadPengguna())->inertiaProps('temporaryPassword');
    auth()->forgetGuards();
    app()->forgetInstance('session.store');
    app('session')->forgetDrivers();
    $this->flushSession();

    $this->post('/login', ['identifier' => 'sari.w', 'password' => $password])->assertRedirect('/dashboard');
});

it('AUTH-03 validasi pembuatan pengguna berbahasa Indonesia', function (array $override, array $galat) {
    $this->actingAs(makeUser(Role::Admin))->from('/settings/users/create')
        ->post('/settings/users', payloadPengguna($override))
        ->assertSessionHasErrors($galat);
})->with([
    'nama kosong' => [['name' => ''], ['name' => 'Nama wajib diisi.']],
    'email buruk' => [['email' => 'bukan-email'], ['email' => 'Format email tidak valid.']],
    'username buruk' => [['username' => 'a b'], ['username' => 'Nama pengguna 3-50 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.']],
    'peran tak dikenal' => [['role' => 'dewa'], ['role' => 'Peran tidak dikenal.']],
    'cabang tak ada' => [['branch_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], ['branch_id' => 'Cabang tidak ditemukan.']],
]);

it('AUTH-03 email dan username harus unik', function () {
    $admin = makeUser(Role::Admin);
    $ada = makeUser(Role::Kasir, attributes: ['email' => 'ada@contoh.test', 'username' => 'sudah.ada']);

    $this->actingAs($admin)->from('/settings/users/create')
        ->post('/settings/users', payloadPengguna(['email' => $ada->email, 'username' => $ada->username]))
        ->assertSessionHasErrors(['email' => 'Email sudah dipakai pengguna lain.', 'username' => 'Nama pengguna sudah dipakai.']);
});

it('AUTH-03 Admin tidak dapat membuat Pemilik, Pemilik dapat', function () {
    $klinik = makeClinic();

    $this->actingAs(makeUser(Role::Admin, $klinik))->post('/settings/users', payloadPengguna(['role' => 'pemilik']))
        ->assertForbidden();

    $this->actingAs(makeUser(Role::Pemilik, $klinik))->post('/settings/users', payloadPengguna(['role' => 'pemilik']))
        ->assertOk();
});

it('AUTH-03 formulir tidak menawarkan peran Pemilik kepada Admin', function () {
    $this->actingAs(makeUser(Role::Admin))->get('/settings/users/create')
        ->assertInertia(fn ($p) => $p->where('roles', fn ($roles) => collect($roles)->pluck('value')->doesntContain('pemilik')));

    $this->actingAs(makeUser(Role::Pemilik))->get('/settings/users/create')
        ->assertInertia(fn ($p) => $p->where('roles', fn ($roles) => collect($roles)->pluck('value')->contains('pemilik')));
});

it('AUTH-03 mengubah data dan peran pengguna, tercatat dengan sebelum dan sesudah', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Resepsionis, $klinik);

    $this->actingAs($admin)->put("/settings/users/{$target->id}", payloadPengguna([
        'name' => 'Nama Baru', 'email' => $target->email, 'username' => $target->username, 'role' => 'kasir',
    ]))->assertRedirect('/settings/users');

    $target->refresh();
    expect($target->name)->toBe('Nama Baru')
        ->and($target->hasRole('kasir'))->toBeTrue()
        ->and($target->hasRole('resepsionis'))->toBeFalse();

    tenant()->set($klinik->id);
    $log = AuditLogModel::query()->where('action', 'user.updated')->firstOrFail();
    expect($log->before['role'])->toBe('resepsionis')->and($log->after['role'])->toBe('kasir');
});

it('AUTH-03 menonaktifkan pengguna: tidak bisa masuk, data dan riwayat tetap utuh', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Kasir, $klinik);

    $this->actingAs($admin)->post("/settings/users/{$target->id}/deactivate")->assertRedirect();

    expect($target->fresh()->is_active)->toBeFalse()
        ->and(DB::table('users')->where('id', $target->id)->whereNull('deleted_at')->exists())->toBeTrue();

    auth()->forgetGuards();
    $this->flushSession();
    $this->post('/login', ['identifier' => $target->email, 'password' => 'kata-sandi-uji-123'])->assertSessionHasErrors('identifier');
    $this->assertGuest();

    tenant()->set($klinik->id);
    expect(AuditLogModel::query()->where('action', 'user.deactivated')->where('subject_id', $target->id)->exists())->toBeTrue();
});

it('AUTH-03 mengaktifkan kembali pengguna', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Kasir, $klinik, attributes: ['is_active' => false]);

    $this->actingAs($admin)->post("/settings/users/{$target->id}/activate")->assertRedirect();

    expect($target->fresh()->is_active)->toBeTrue();
});

it('AUTH-03 reset kata sandi: sandi lama tidak berlaku, sandi sementara baru berlaku, tercatat tanpa isi sandi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Kasir, $klinik);

    $baru = $this->actingAs($admin)->post("/settings/users/{$target->id}/reset-password")
        ->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Users/Credential'))
        ->inertiaProps('temporaryPassword');

    $target->refresh();
    expect(Hash::check('kata-sandi-uji-123', $target->password))->toBeFalse()
        ->and(Hash::check($baru, $target->password))->toBeTrue();

    tenant()->set($klinik->id);
    $audit = AuditLogModel::query()->where('action', 'user.password_reset')->firstOrFail();
    expect(json_encode($audit->toArray()))->not->toContain($baru);
});

it('AUTH-03 Admin tidak dapat mengubah, menonaktifkan, atau mereset Pemilik', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $pemilik = makeUser(Role::Pemilik, $klinik);

    $this->actingAs($admin)->put("/settings/users/{$pemilik->id}", payloadPengguna(['email' => $pemilik->email, 'username' => $pemilik->username, 'role' => 'admin']))->assertForbidden();
    $this->actingAs($admin)->post("/settings/users/{$pemilik->id}/deactivate")->assertForbidden();
    $this->actingAs($admin)->post("/settings/users/{$pemilik->id}/reset-password")->assertForbidden();

    expect($pemilik->fresh()->hasRole('pemilik'))->toBeTrue()->and($pemilik->fresh()->is_active)->toBeTrue();
});

it('AUTH-03 klinik harus selalu punya satu Pemilik aktif', function () {
    $klinik = makeClinic();
    $pemilikA = makeUser(Role::Pemilik, $klinik);
    $pemilikB = makeUser(Role::Pemilik, $klinik);

    // menonaktifkan Pemilik B oleh Pemilik A boleh karena A masih aktif
    $this->actingAs($pemilikA)->post("/settings/users/{$pemilikB->id}/deactivate")->assertRedirect();

    // menurunkan peran Pemilik A (pemilik terakhir) ditolak, begitu pula menonaktifkan pemilik lain yang tersisa
    $this->actingAs($pemilikB->refresh()->forceFill(['is_active' => true]))->put("/settings/users/{$pemilikA->id}", payloadPengguna([
        'email' => $pemilikA->email, 'username' => $pemilikA->username, 'role' => 'admin',
    ]))->assertSessionHasErrors('domain');

    expect($pemilikA->fresh()->hasRole('pemilik'))->toBeTrue();
});

it('AUTH-03 pengguna tidak dapat menonaktifkan dirinya sendiri', function () {
    $klinik = makeClinic();
    $pemilik = makeUser(Role::Pemilik, $klinik);
    makeUser(Role::Pemilik, $klinik);

    $this->actingAs($pemilik)->post("/settings/users/{$pemilik->id}/deactivate")->assertForbidden();
});

it('AUTH-03 peran tanpa izin pengguna ditolak di semua route', function (Role $peran) {
    $klinik = makeClinic();
    $user = makeUser($peran, $klinik);
    $target = makeUser(Role::Kasir, $klinik);

    $this->actingAs($user)->get('/settings/users')->assertForbidden();
    $this->actingAs($user)->get('/settings/users/create')->assertForbidden();
    $this->actingAs($user)->post('/settings/users', payloadPengguna())->assertForbidden();
    $this->actingAs($user)->put("/settings/users/{$target->id}", payloadPengguna())->assertForbidden();
    $this->actingAs($user)->post("/settings/users/{$target->id}/deactivate")->assertForbidden();
    $this->actingAs($user)->post("/settings/users/{$target->id}/reset-password")->assertForbidden();
})->with([Role::Resepsionis, Role::Dokter, Role::Perawat, Role::Kasir]);

it('AUTH-03 Admin klinik A tidak dapat melihat atau mengubah pengguna klinik B (IDOR)', function () {
    $adminA = makeUser(Role::Admin, makeClinic('A'));
    $targetB = makeUser(Role::Kasir, makeClinic('B'));

    $this->actingAs($adminA)->get("/settings/users/{$targetB->id}/edit")->assertNotFound();
    $this->actingAs($adminA)->post("/settings/users/{$targetB->id}/deactivate")->assertNotFound();
    $this->actingAs($adminA)->post("/settings/users/{$targetB->id}/reset-password")->assertNotFound();

    expect($targetB->fresh()->is_active)->toBeTrue();
});

it('AUTH-03 halaman ubah pengguna menampilkan data tanpa kata sandi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Kasir, $klinik);

    $response = $this->actingAs($admin)->get("/settings/users/{$target->id}/edit")->assertOk();

    expect(json_encode($response->inertiaProps()))->not->toContain('password');
});
