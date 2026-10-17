<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Application\TwoFactor\TwoFactorStore;
use Src\IdentityAccess\Domain\Role;
use Src\IdentityAccess\Domain\TwoFactor\Totp;
use Src\IdentityAccess\Infrastructure\Eloquent\User;

const KATA_SANDI_UJI = 'kata-sandi-uji-123';

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 08:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function kodeSekarang(string $rahasia): string
{
    return Totp::code($rahasia, Totp::step(now()->getTimestamp()));
}

/**
 * Pengguna dengan verifikasi dua langkah aktif lewat alur sebenarnya.
 *
 * @return array{user: User, rahasia: string, kode: list<string>}
 */
function penggunaDuaLangkah(Role $peran = Role::Pemilik, ?object $klinik = null): array
{
    $user = makeUser($peran, $klinik);
    test()->actingAs($user)->post('/account/two-factor/start')->assertSessionHasNoErrors();
    $rahasia = (string) app(TwoFactorStore::class)->pendingSecret($user->id);
    test()->actingAs($user)->post('/account/two-factor/confirm', ['password' => KATA_SANDI_UJI, 'code' => kodeSekarang($rahasia)])->assertSessionHasNoErrors();
    $kode = session('recovery_codes');
    test()->flushSession();
    auth()->logout();
    // Kode saat aktivasi sudah terpakai; langkah berikutnya diperlukan untuk masuk.
    Carbon::setTestNow(now()->addSeconds(31));

    return ['user' => $user, 'rahasia' => $rahasia, 'kode' => $kode];
}

function masukDenganSandi(User $user)
{
    return test()->post('/login', ['identifier' => $user->email, 'password' => KATA_SANDI_UJI]);
}

it('AUTH-05 Pemilik mengaktifkan verifikasi dua langkah dan mendapat sepuluh kode pemulihan sekali tampil', function () {
    $user = makeUser(Role::Pemilik);
    $this->actingAs($user)->post('/account/two-factor/start')->assertRedirect('/account/two-factor');

    $rahasia = (string) app(TwoFactorStore::class)->pendingSecret($user->id);
    $baris = DB::table('users')->where('id', $user->id)->first();
    expect($baris->two_factor_pending_encrypted)->not->toBeNull()->and($baris->two_factor_pending_encrypted)->not->toContain($rahasia);
    $this->get('/account/two-factor')->assertInertia(fn ($p) => $p->component('Account/TwoFactor')->where('status.pending', true)->where('status.enabled', false)
        ->where('status.secret', trim(chunk_split($rahasia, 4, ' ')))->where('status.uri', fn ($uri) => str_contains($uri, 'secret='.$rahasia)));

    $this->post('/account/two-factor/confirm', ['password' => KATA_SANDI_UJI, 'code' => kodeSekarang($rahasia)])->assertSessionHasNoErrors()->assertRedirect('/account/two-factor');

    $kode = session('recovery_codes');
    $baris = DB::table('users')->where('id', $user->id)->first();
    expect($kode)->toHaveCount(10)->and($baris->two_factor_confirmed_at)->not->toBeNull()->and($baris->two_factor_pending_encrypted)->toBeNull()
        ->and($baris->two_factor_secret_encrypted)->not->toContain($rahasia)
        ->and($baris->two_factor_recovery_hashes)->not->toContain($kode[0]);
    expect(DB::table('audit_logs')->whereIn('action', ['auth.two_factor_setup_started', 'auth.two_factor_enabled'])->count())->toBe(2);

    $this->get('/account/two-factor')->assertInertia(fn ($p) => $p->where('status.enabled', true)->where('status.recovery_remaining', 10)->where('status.secret', null)->where('recoveryCodes', $kode));
});

it('AUTH-05 aktivasi ditolak bila kata sandi atau kode salah', function () {
    $user = makeUser(Role::Admin);
    $this->actingAs($user)->post('/account/two-factor/start');
    $rahasia = (string) app(TwoFactorStore::class)->pendingSecret($user->id);

    $this->post('/account/two-factor/confirm', ['password' => 'salah-sekali-123', 'code' => kodeSekarang($rahasia)])->assertSessionHasErrors('password');
    $this->post('/account/two-factor/confirm', ['password' => KATA_SANDI_UJI, 'code' => '000000'])->assertSessionHasErrors('code');

    expect(DB::table('users')->where('id', $user->id)->value('two_factor_confirmed_at'))->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'auth.two_factor_failed')->count())->toBe(2);
});

it('AUTH-05 hanya Pemilik dan Admin yang dapat mengelola verifikasi dua langkah', function () {
    $klinik = makeClinic();

    foreach ([Role::Dokter, Role::Perawat, Role::Resepsionis, Role::Kasir] as $peran) {
        $this->actingAs(makeUser($peran, $klinik))->get('/account/two-factor')->assertForbidden();
        $this->post('/account/two-factor/start')->assertForbidden();
    }
    $this->actingAs(makeUser(Role::Admin, $klinik))->get('/account/two-factor')->assertOk();
    $this->actingAs(makeUser(Role::Pemilik, $klinik))->get('/account/two-factor')->assertOk();
});

it('AUTH-05 pengguna tanpa verifikasi dua langkah masuk seperti biasa', function () {
    $user = makeUser(Role::Pemilik);

    masukDenganSandi($user)->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('AUTH-05 kata sandi benar belum memulai sesi dan kode yang sah menyelesaikan masuk', function () {
    ['user' => $user, 'rahasia' => $rahasia] = penggunaDuaLangkah();

    masukDenganSandi($user)->assertRedirect('/login/two-factor');
    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect('/login');
    expect(DB::table('audit_logs')->where('action', 'auth.login')->count())->toBe(0)->and(DB::table('audit_logs')->where('action', 'auth.password_accepted')->count())->toBe(1);

    $this->get('/login/two-factor')->assertOk()->assertInertia(fn ($p) => $p->component('Auth/TwoFactor'));
    $this->post('/login/two-factor', ['code' => kodeSekarang($rahasia)])->assertSessionHasNoErrors()->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    $login = DB::table('audit_logs')->where('action', 'auth.login')->first();
    expect($login->actor_id)->toBe($user->id)->and($login->after)->toContain('totp')->and(DB::table('users')->where('id', $user->id)->value('last_login_at'))->not->toBeNull();
});

it('AUTH-05 kode yang salah ditolak dan kode TOTP tidak dapat dipakai dua kali', function () {
    ['user' => $user, 'rahasia' => $rahasia] = penggunaDuaLangkah();

    masukDenganSandi($user);
    $this->post('/login/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $kode = kodeSekarang($rahasia);
    $this->post('/login/two-factor', ['code' => $kode])->assertRedirect('/dashboard');
    $this->post('/logout');

    masukDenganSandi($user)->assertRedirect('/login/two-factor');
    $this->post('/login/two-factor', ['code' => $kode])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('AUTH-05 percobaan kode dibatasi lima kali per menit', function () {
    ['user' => $user] = penggunaDuaLangkah();
    masukDenganSandi($user);

    foreach (range(1, 5) as $i) {
        $this->post('/login/two-factor', ['code' => '00000'.$i])->assertSessionHasErrors('code');
    }
    $this->post('/login/two-factor', ['code' => '000009'])->assertSessionHasErrors('code');
    $pesan = session('errors')->first('code');

    expect($pesan)->toContain('Terlalu banyak')->and(DB::table('audit_logs')->where('action', 'auth.two_factor_failed')->count())->toBe(5);
});

it('AUTH-05 kode pemulihan masuk sekali lalu hangus', function () {
    ['user' => $user, 'kode' => $kode] = penggunaDuaLangkah();

    masukDenganSandi($user);
    $this->post('/login/two-factor', ['code' => strtoupper($kode[0])])->assertRedirect('/dashboard');
    $this->post('/logout');
    expect(DB::table('audit_logs')->where('action', 'auth.login')->first()->after)->toContain('recovery');

    masukDenganSandi($user);
    $this->post('/login/two-factor', ['code' => $kode[0]])->assertSessionHasErrors('code');
    $this->post('/login/two-factor', ['code' => $kode[1]])->assertRedirect('/dashboard');
    $this->actingAs($user)->get('/account/two-factor')->assertInertia(fn ($p) => $p->where('status.recovery_remaining', 8));
});

it('AUTH-05 langkah kedua tidak dapat dibuka tanpa kata sandi benar dan kedaluwarsa setelah lima menit', function () {
    ['user' => $user, 'rahasia' => $rahasia] = penggunaDuaLangkah();

    $this->get('/login/two-factor')->assertRedirect('/login');
    $this->post('/login/two-factor', ['code' => kodeSekarang($rahasia)])->assertRedirect('/login');
    $this->assertGuest();

    masukDenganSandi($user);
    Carbon::setTestNow(now()->addMinutes(6));
    $this->withSession(['two_factor' => ['user_id' => $user->id, 'at' => now()->subMinutes(6)->getTimestamp()]])
        ->post('/login/two-factor', ['code' => kodeSekarang($rahasia)])->assertRedirect('/login');
    $this->assertGuest();
});

it('AUTH-05 pengguna nonaktif tidak dapat menyelesaikan langkah kedua', function () {
    ['user' => $user, 'rahasia' => $rahasia] = penggunaDuaLangkah();
    masukDenganSandi($user);
    DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

    $this->post('/login/two-factor', ['code' => kodeSekarang($rahasia)])->assertRedirect('/login');

    $this->assertGuest();
});

it('AUTH-05 mematikan verifikasi dua langkah meminta kata sandi dan kode yang sah', function () {
    ['user' => $user, 'rahasia' => $rahasia] = penggunaDuaLangkah();
    $this->actingAs($user);

    $this->post('/account/two-factor/disable', ['password' => 'salah-sekali-123', 'code' => kodeSekarang($rahasia)])->assertSessionHasErrors('password');
    $this->post('/account/two-factor/disable', ['password' => KATA_SANDI_UJI, 'code' => '000000'])->assertSessionHasErrors('code');
    expect(DB::table('users')->where('id', $user->id)->value('two_factor_confirmed_at'))->not->toBeNull();

    $this->post('/account/two-factor/disable', ['password' => KATA_SANDI_UJI, 'code' => kodeSekarang($rahasia)])->assertSessionHasNoErrors();

    $baris = DB::table('users')->where('id', $user->id)->first();
    expect($baris->two_factor_confirmed_at)->toBeNull()->and($baris->two_factor_secret_encrypted)->toBeNull()->and($baris->two_factor_recovery_hashes)->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'auth.two_factor_disabled')->count())->toBe(1);
    $this->post('/logout');
    masukDenganSandi($user)->assertRedirect('/dashboard');
});

it('AUTH-05 kode pemulihan baru membatalkan kode lama', function () {
    ['user' => $user, 'rahasia' => $rahasia, 'kode' => $lama] = penggunaDuaLangkah();
    $this->actingAs($user);

    $this->post('/account/two-factor/recovery-codes', ['password' => KATA_SANDI_UJI, 'code' => kodeSekarang($rahasia)])->assertSessionHasNoErrors();
    $baru = session('recovery_codes');
    expect($baru)->toHaveCount(10)->and(array_intersect($baru, $lama))->toBe([]);

    $this->post('/logout');
    masukDenganSandi($user);
    $this->post('/login/two-factor', ['code' => $lama[0]])->assertSessionHasErrors('code');
    $this->post('/login/two-factor', ['code' => $baru[0]])->assertRedirect('/dashboard');
});

it('AUTH-05 Pemilik mematikan verifikasi dua langkah Admin yang kehilangan perangkat, tetapi Admin tidak dapat melakukannya pada Pemilik', function () {
    $klinik = makeClinic();
    ['user' => $admin] = penggunaDuaLangkah(Role::Admin, $klinik);
    ['user' => $pemilik] = penggunaDuaLangkah(Role::Pemilik, $klinik);

    $this->actingAs($admin)->post("/settings/users/{$pemilik->id}/reset-two-factor")->assertForbidden();
    expect(DB::table('users')->where('id', $pemilik->id)->value('two_factor_confirmed_at'))->not->toBeNull();

    $this->actingAs($pemilik)->post("/settings/users/{$admin->id}/reset-two-factor")->assertSessionHasNoErrors();
    $baris = DB::table('users')->where('id', $admin->id)->first();
    expect($baris->two_factor_confirmed_at)->toBeNull()->and($baris->two_factor_secret_encrypted)->toBeNull()
        ->and(DB::table('audit_logs')->where('action', 'user.two_factor_reset')->where('subject_id', $admin->id)->count())->toBe(1);
    $this->actingAs($pemilik)->get('/settings/users')->assertInertia(fn ($p) => $p->where('users', fn ($daftar) => collect($daftar)->firstWhere('id', $pemilik->id)['twoFactorEnabled'] === true));
});

it('AUTH-05 rahasia dan kode tidak muncul di props bersama atau daftar pengguna', function () {
    ['user' => $user, 'rahasia' => $rahasia] = penggunaDuaLangkah();

    $json = $this->actingAs($user)->get('/settings/users')->getContent();

    expect($json)->not->toContain($rahasia)->and($json)->not->toContain('two_factor_secret');
});
