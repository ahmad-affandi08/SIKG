<?php

declare(strict_types=1);

use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Domain\Role;

it('AUTH-04 pengguna aktif tetap masuk selama masih dalam batas bawaan 30 menit', function () {
    $user = makeUser(Role::Admin);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->travel(29)->minutes();
    $this->get('/dashboard')->assertOk();
});

it('AUTH-04 sesi berakhir setelah 30 menit tidak aktif dan pengguna diarahkan ke login dengan pesan', function () {
    $user = makeUser(Role::Admin);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->travel(31)->minutes();

    $this->get('/dashboard')->assertRedirect('/login')->assertSessionHas('error', 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
    $this->assertGuest();
});

it('AUTH-04 aktivitas memperpanjang sesi (batas dihitung dari permintaan terakhir)', function () {
    $user = makeUser(Role::Admin);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->travel(20)->minutes();
    $this->get('/dashboard')->assertOk();
    $this->travel(20)->minutes();
    $this->get('/dashboard')->assertOk();
});

it('AUTH-04 permintaan JSON mendapat 401 dengan kode mesin saat sesi berakhir', function () {
    $user = makeUser(Role::Admin);

    $this->actingAs($user)->getJson('/dashboard')->assertOk();
    $this->travel(31)->minutes();

    $this->getJson('/dashboard')->assertUnauthorized()->assertJson(['code' => 'auth.session_expired']);
});

it('AUTH-04 batas sesi dapat diatur klinik', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Admin, $klinik);
    $pengaturan = $klinik->clinicSettings()->toArray();
    $pengaturan['session_timeout_minutes'] = 60;
    $klinik->forceFill(['settings' => $pengaturan])->save();

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->travel(45)->minutes();
    $this->get('/dashboard')->assertOk();
    $this->travel(61)->minutes();
    $this->get('/dashboard')->assertRedirect('/login');
});

it('AUTH-04 batas sesi klinik tidak melampaui batas tertinggi server', function () {
    config(['sikg.session_max_minutes' => 40]);
    $klinik = makeClinic();
    $user = makeUser(Role::Admin, $klinik);
    $pengaturan = $klinik->clinicSettings()->toArray();
    $pengaturan['session_timeout_minutes'] = 120;
    $klinik->forceFill(['settings' => $pengaturan])->save();

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->travel(41)->minutes();

    $this->get('/dashboard')->assertRedirect('/login');
});

it('AUTH-04 klinik lain tidak terpengaruh pengaturan klinik ini', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $pengaturan = $a->clinicSettings()->toArray();
    $pengaturan['session_timeout_minutes'] = 120;
    $a->forceFill(['settings' => $pengaturan])->save();
    $userB = makeUser(Role::Admin, $b);

    $this->actingAs($userB)->get('/dashboard')->assertOk();
    $this->travel(31)->minutes();

    $this->get('/dashboard')->assertRedirect('/login');
    expect(ClinicModel::withoutGlobalScopes()->find($a->id)->clinicSettings()->sessionTimeoutMinutes)->toBe(120);
});
