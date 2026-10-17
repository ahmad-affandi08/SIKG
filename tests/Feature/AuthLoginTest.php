<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    RateLimiter::clear('login');
});

it('AUTH-01 masuk dengan email dan kata sandi', function () {
    $user = makeUser(Role::Resepsionis);

    $this->post('/login', ['identifier' => $user->email, 'password' => 'kata-sandi-uji-123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('AUTH-01 masuk dengan username', function () {
    $user = makeUser(Role::Kasir);

    $this->post('/login', ['identifier' => $user->username, 'password' => 'kata-sandi-uji-123'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

it('AUTH-01 kata sandi di-hash, tidak disimpan polos', function () {
    $user = makeUser();

    $stored = DB::table('users')->where('id', $user->id)->value('password');

    expect($stored)->not->toBe('kata-sandi-uji-123')
        ->and(password_verify('kata-sandi-uji-123', (string) $stored))->toBeTrue();
});

it('AUTH-01 kata sandi salah ditolak dengan pesan umum', function () {
    $user = makeUser();

    $this->from('/login')
        ->post('/login', ['identifier' => $user->email, 'password' => 'salah'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['identifier' => 'Nama pengguna atau kata sandi salah.']);

    $this->assertGuest();
});

it('AUTH-01 akun yang tidak ada mendapat pesan yang sama (tidak membocorkan akun)', function () {
    $this->from('/login')
        ->post('/login', ['identifier' => 'tidak.ada@contoh.test', 'password' => 'apa-saja'])
        ->assertSessionHasErrors(['identifier' => 'Nama pengguna atau kata sandi salah.']);

    $this->assertGuest();
});

it('AUTH-03 pengguna nonaktif tidak bisa masuk dan riwayatnya tetap utuh', function () {
    $user = makeUser(attributes: ['is_active' => false]);

    $this->post('/login', ['identifier' => $user->email, 'password' => 'kata-sandi-uji-123'])
        ->assertSessionHasErrors('identifier');

    $this->assertGuest();
    expect(DB::table('users')->where('id', $user->id)->exists())->toBeTrue();
});

it('AUTH-03 pengguna yang dinonaktifkan saat sesi berjalan tidak lagi dimuat dari sesi', function () {
    $user = makeUser();
    $provider = auth()->guard('web')->getProvider();

    expect($provider->retrieveById($user->id)?->getAuthIdentifier())->toBe($user->id);

    $user->forceFill(['is_active' => false])->save();

    expect($provider->retrieveById($user->id))->toBeNull();
});

it('AUTH-01 percobaan gagal dibatasi 5 kali per menit per akun dan IP', function () {
    $user = makeUser();
    $lain = makeUser();

    foreach (range(1, 5) as $i) {
        $this->from('/login')->post('/login', ['identifier' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors(['identifier' => 'Nama pengguna atau kata sandi salah.']);
    }

    // Percobaan ke-6 diblokir walau kata sandi benar.
    $this->from('/login')->post('/login', ['identifier' => $user->email, 'password' => 'kata-sandi-uji-123'])
        ->assertSessionHasErrors('identifier');
    $this->assertGuest();
    expect(session('errors')->first('identifier'))->toStartWith('Terlalu banyak percobaan masuk.');

    // Akun lain dari IP yang sama tidak ikut diblokir.
    $this->post('/login', ['identifier' => $lain->email, 'password' => 'kata-sandi-uji-123'])->assertRedirect('/dashboard');
});

it('AUTH-01 login berhasil mereset hitungan gagal dan menulis last_login_at serta audit', function () {
    $user = makeUser();

    foreach (range(1, 3) as $i) {
        $this->post('/login', ['identifier' => $user->email, 'password' => 'salah']);
    }
    $this->post('/login', ['identifier' => $user->email, 'password' => 'kata-sandi-uji-123'])->assertRedirect('/dashboard');

    expect($user->fresh()->last_login_at)->not->toBeNull();

    tenant()->set($user->clinic_id);
    expect(AuditLogModel::query()->where('actor_id', $user->id)->where('action', 'auth.login')->count())->toBe(1)
        ->and(AuditLogModel::query()->where('actor_id', $user->id)->where('action', 'auth.login_failed')->count())->toBe(3);
});

it('AUTH-01 kunci pembatas tidak memuat email mentah', function () {
    $user = makeUser(attributes: ['email' => 'rahasia.nama@contoh.test']);

    $this->post('/login', ['identifier' => $user->email, 'password' => 'salah']);

    $keys = DB::table('cache')->pluck('key')->implode(' ');
    expect($keys)->not->toContain('rahasia.nama');
});

it('AUTH-01 keluar mengakhiri sesi', function () {
    $user = makeUser();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('AUTH-01 tanpa login halaman dilindungi diarahkan ke login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->post('/logout')->assertRedirect('/login');
});

it('AUTH-01 halaman login dirender untuk tamu dan pengguna masuk dialihkan', function () {
    $this->get('/login')->assertOk()->assertSee('data-page', false);

    $this->actingAs(makeUser())->get('/login')->assertRedirect();
});

it('AUTH-01 form login memvalidasi dengan pesan Indonesia', function () {
    $this->from('/login')->post('/login', [])
        ->assertSessionHasErrors([
            'identifier' => 'Email atau nama pengguna wajib diisi.',
            'password' => 'Kata sandi wajib diisi.',
        ]);
});

it('AUTH-04 sesi berakhir setelah 30 menit tidak aktif secara bawaan', function () {
    expect(config('session.lifetime'))->toBe(30)
        ->and(config('session.expire_on_close'))->toBeFalse();
});

it('AUTH-04 cookie sesi Secure, HttpOnly, dan SameSite=Lax', function () {
    expect(config('session.secure'))->toBeTrue()
        ->and(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});

it('AUTH-04 sesi di database kedaluwarsa setelah tidak aktif', function () {
    config(['session.driver' => 'database', 'session.lifetime' => 30]);
    $user = makeUser();

    $login = $this->post('/login', ['identifier' => $user->email, 'password' => 'kata-sandi-uji-123']);
    $cookie = $login->getCookie(config('session.cookie'), false);
    expect($cookie)->not->toBeNull();

    $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get('/dashboard')->assertOk();

    $this->travel(31)->minutes();
    auth()->forgetGuards();
    app()->forgetInstance('session.store');
    app('session')->forgetDrivers(); // permintaan baru memuat sesi dari database, bukan dari objek tes yang sama
    $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get('/dashboard')->assertRedirect('/login');
});
