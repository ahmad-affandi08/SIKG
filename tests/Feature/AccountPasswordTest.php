<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Infrastructure\Eloquent\User;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    RateLimiter::clear('login');
});

const LAMA = 'kata-sandi-uji-123';
const BARU = 'KataSandiBaru2024';

function ganti(array $o = [])
{
    return test()->put('/account/password', ['current_password' => LAMA, 'password' => BARU, 'password_confirmation' => BARU, ...$o]);
}

function sesiPalsu(string $id, string $userId): void
{
    DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1', 'user_agent' => 'uji', 'payload' => '', 'last_activity' => time()]);
}

it('AUTH-06 pengguna mengganti kata sandinya sendiri; hanya hash tersimpan dan audit tidak memuat kata sandi', function () {
    $user = makeUser(Role::Kasir);

    $this->actingAs($user)->get('/account/password')->assertOk()->assertInertia(fn ($p) => $p->component('Account/Password')->where('forced', false)->where('minLength', 10));
    ganti()->assertSessionHasNoErrors()->assertRedirect();

    tenant()->set($user->clinic_id);
    $user->refresh();
    expect(Hash::check(BARU, $user->password))->toBeTrue()->and(Hash::check(LAMA, $user->password))->toBeFalse()
        ->and($user->password_changed_at)->not->toBeNull()->and($user->must_change_password)->toBeFalse();
    $audit = AuditLogModel::query()->where('action', 'user.password_changed')->where('subject_id', $user->id)->firstOrFail();
    expect($audit->actor_id)->toBe($user->id)->and(json_encode($audit->after))->not->toContain(BARU)->not->toContain(LAMA);
});

it('AUTH-06 kata sandi saat ini yang salah ditolak, tercatat gagal, dan kata sandi tidak berubah', function () {
    $user = makeUser(Role::Kasir);

    $this->actingAs($user);
    ganti(['current_password' => 'salah-total-123'])->assertSessionHasErrors(['current_password' => 'Kata sandi saat ini salah.']);

    tenant()->set($user->clinic_id);
    expect(Hash::check(LAMA, $user->refresh()->password))->toBeTrue()
        ->and(AuditLogModel::query()->where('action', 'user.password_change_failed')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('AUTH-06 kata sandi baru wajib berbeda, minimal 10 karakter, memuat huruf dan angka, dan terkonfirmasi', function () {
    $user = makeUser(Role::Kasir);
    $this->actingAs($user);

    ganti(['password' => LAMA, 'password_confirmation' => LAMA])->assertSessionHasErrors('password');
    ganti(['password' => 'Pendek1', 'password_confirmation' => 'Pendek1'])->assertSessionHasErrors(['password' => 'Kata sandi baru minimal 10 karakter.']);
    ganti(['password' => 'HurufSajaPanjang', 'password_confirmation' => 'HurufSajaPanjang'])->assertSessionHasErrors(['password' => 'Kata sandi baru harus memuat angka.']);
    ganti(['password' => '12345678901234', 'password_confirmation' => '12345678901234'])->assertSessionHasErrors(['password' => 'Kata sandi baru harus memuat huruf.']);
    ganti(['password_confirmation' => 'Beda12345678'])->assertSessionHasErrors(['password' => 'Konfirmasi kata sandi tidak sama.']);
    ganti(['current_password' => ''])->assertSessionHasErrors('current_password');

    expect(Hash::check(LAMA, $user->refresh()->password))->toBeTrue();
});

it('AUTH-06 mengganti kata sandi mengakhiri sesi lain pengguna itu, bukan sesi orang lain', function () {
    $user = makeUser(Role::Kasir);
    $lain = makeUser(Role::Kasir);
    sesiPalsu('sesi-lain-1', $user->id);
    sesiPalsu('sesi-lain-2', $user->id);
    sesiPalsu('sesi-orang-lain', $lain->id);

    $this->actingAs($user);
    ganti()->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $user->id)->whereIn('id', ['sesi-lain-1', 'sesi-lain-2'])->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'sesi-orang-lain')->exists())->toBeTrue();
});

it('AUTH-06 percobaan ganti kata sandi dibatasi', function () {
    $user = makeUser(Role::Kasir);
    $this->actingAs($user);

    for ($i = 0; $i < 6; $i++) {
        ganti(['current_password' => 'salah-total-123']);
    }

    ganti(['current_password' => LAMA])->assertStatus(429);
    expect(Hash::check(LAMA, $user->refresh()->password))->toBeTrue();
});

it('AUTH-06 halaman ganti kata sandi menuntut login', function () {
    $this->get('/account/password')->assertRedirect('/login');
    $this->put('/account/password', ['current_password' => 'x', 'password' => 'y'])->assertRedirect('/login');
});

it('AUTH-06 akun baru wajib mengganti kata sandi sementara sebelum memakai fitur lain', function () {
    $admin = makeUser(Role::Admin);
    $password = $this->actingAs($admin)->post('/settings/users', ['name' => 'Sari', 'email' => 'sari@contoh.test', 'username' => 'sari.w', 'role' => 'resepsionis', 'branch_id' => ''])->inertiaProps('temporaryPassword');
    tenant()->set($admin->clinic_id);
    $sari = User::query()->where('username', 'sari.w')->firstOrFail();
    expect($sari->must_change_password)->toBeTrue();

    $this->actingAs($sari);
    $this->get('/dashboard')->assertRedirect('/account/password');
    $this->get('/patients')->assertRedirect('/account/password');
    $this->getJson('/internal-api/queue')->assertStatus(403)->assertJsonPath('code', 'auth.password_change_required');
    $this->get('/account/password')->assertOk()->assertInertia(fn ($p) => $p->where('forced', true));

    $this->put('/account/password', ['current_password' => $password, 'password' => BARU, 'password_confirmation' => BARU])->assertRedirect('/dashboard');
    expect($sari->refresh()->must_change_password)->toBeFalse();
    $this->get('/dashboard')->assertOk();
});

it('AUTH-06 keluar tetap dapat dilakukan saat wajib ganti kata sandi', function () {
    $user = makeUser(Role::Kasir, null, null, ['must_change_password' => true]);

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
});

it('AUTH-06 reset kata sandi oleh Admin mewajibkan ganti dan mengakhiri sesi lama pengguna itu', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $target = makeUser(Role::Kasir, $klinik);
    sesiPalsu('sesi-target', $target->id);

    $this->actingAs($admin)->post("/settings/users/{$target->id}/reset-password")->assertOk();

    expect($target->refresh()->must_change_password)->toBeTrue()->and(DB::table('sessions')->where('id', 'sesi-target')->exists())->toBeFalse();
});

it('AUTH-06 pengguna nonaktif tidak dapat mengganti kata sandi', function () {
    $user = makeUser(Role::Kasir, null, null, ['is_active' => false]);

    $this->actingAs($user)->get('/account/password')->assertForbidden();
});
