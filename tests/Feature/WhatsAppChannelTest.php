<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\IdentityAccess\Domain\Role;
use Src\Integration\Application\Reminder\WhatsAppBookingCodeSender;
use Src\Integration\Application\Reminder\WhatsAppGateway;
use Src\Integration\Domain\Reminder\EndpointGuard;
use Src\Integration\Domain\Reminder\InvalidReminder;
use Src\Integration\Infrastructure\ChannelStaffWhatsApp;
use Src\Integration\Infrastructure\HostResolver;
use Src\Scheduling\Contracts\BookingCodeNotSent;
use Src\Scheduling\Contracts\BookingCodeSender;
use Src\Shared\Application\Notifications\StaffWhatsApp;
use Src\Shared\Domain\WhatsAppNumber;

/** Mengganti seluruh stub HTTP (stub lama yang cocok akan menang bila hanya ditambah). */
function fakeHttp(array $stubs = []): void
{
    Http::swap(new Factory);
    Http::fake($stubs);
}

/** Penyelesai nama server tetap (tanpa jaringan): alamat publik, atau privat bila diminta. */
function resolverTetap(array $ips = ['93.184.216.34']): void
{
    // Pelayan yang sudah dibangun memegang penyelesai lama; bangun ulang saat dibutuhkan.
    foreach ([WhatsAppGateway::class, ChannelStaffWhatsApp::class, WhatsAppBookingCodeSender::class, StaffWhatsApp::class, BookingCodeSender::class] as $kelas) {
        app()->forgetInstance($kelas);
    }
    // Pengendali rute yang sudah dibuat disimpan oleh rute dan ikut memegang pelayan lama.
    foreach (app('router')->getRoutes() as $rute) {
        $rute->flushController();
    }
    app()->instance(HostResolver::class, new class($ips) implements HostResolver
    {
        public function __construct(private array $ips) {}

        public function resolve(string $host): array
        {
            return $this->ips;
        }
    });
}

/** @param array<string, mixed> $o */
function simpanWa(array $o)
{
    $respons = test()->put('/settings/whatsapp', $o);
    // Konteks klinik dibersihkan di akhir permintaan; pengujian langsung memerlukannya kembali.
    tenant()->set((string) DB::table('clinics')->value('id'));

    return $respons;
}

function siapWa(): array
{
    $klinik = makeClinic();
    // Pesan uji hanya boleh ke nomor staf klinik.
    $klinik->forceFill(['settings' => ['notify_whatsapp' => ['6281234567890', '628123456789']]])->save();
    tenant()->set($klinik->id);
    $admin = makeUser(Role::Admin, $klinik);
    test()->actingAs($admin);
    Http::swap(new Factory);

    return ['klinik' => $klinik, 'admin' => $admin];
}

it('SYS-05 hanya pemegang izin pengaturan membuka dan mengubah pengaturan WhatsApp', function () {
    $klinik = makeClinic();
    foreach ([Role::Resepsionis, Role::Kasir, Role::Perawat, Role::Dokter] as $peran) {
        $this->actingAs(makeUser($peran, $klinik))->get('/settings/whatsapp')->assertForbidden();
        $this->put('/settings/whatsapp', ['provider' => 'link'])->assertForbidden();
        $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertForbidden();
    }
    $this->actingAs(makeUser(Role::Admin, $klinik))->get('/settings/whatsapp')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/WhatsApp')->has('providers', 5)->where('settings.provider', 'link'));
});

it('SYS-05 pilihan penyedia memuat WhatsApp Resmi dan tiga penyedia tidak resmi', function () {
    siapWa();

    $this->get('/settings/whatsapp')->assertInertia(fn ($p) => $p
        ->where('providers.0.value', 'link')
        ->where('providers.1.value', 'meta')->where('providers.1.official', true)
        ->where('providers.2.value', 'fonnte')->where('providers.2.official', false)
        ->where('providers.3.value', 'wablas')->where('providers.4.value', 'waha'));
});

it('SYS-05 penyedia diwajibkan mengisi kredensialnya dan token tersimpan terenkripsi tanpa pernah ditampilkan', function () {
    siapWa();

    simpanWa(['provider' => 'meta'])->assertSessionHas('error_code', 'reminder.invalid');
    simpanWa(['provider' => 'fonnte'])->assertSessionHas('error_code', 'reminder.invalid');
    simpanWa(['provider' => 'wablas', 'token' => 'tk'])->assertSessionHas('error_code', 'reminder.invalid');
    simpanWa(['provider' => 'tidak-ada'])->assertSessionHasErrors('provider');
    expect(DB::table('reminder_settings')->count())->toBe(0);

    simpanWa(['provider' => 'fonnte', 'token' => 'TOKEN-FONNTE-RAHASIA'])->assertSessionHasNoErrors();

    $baris = DB::table('reminder_settings')->first();
    expect($baris->provider)->toBe('fonnte')->and($baris->mode)->toBe('api')->and($baris->api_token_encrypted)->not->toContain('TOKEN-FONNTE-RAHASIA');
    $halaman = $this->get('/settings/whatsapp');
    $halaman->assertInertia(fn ($p) => $p->where('settings.has_token', true)->where('settings.ready', true)->missing('settings.token'));
    expect($halaman->getContent())->not->toContain('TOKEN-FONNTE-RAHASIA')
        ->and(json_encode(DB::table('audit_logs')->where('action', 'whatsapp.settings_updated')->get()))->not->toContain('TOKEN-FONNTE-RAHASIA');

    simpanWa(['provider' => 'fonnte', 'token' => ''])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('api_token_encrypted'))->toBe($baris->api_token_encrypted);

    simpanWa(['provider' => 'link'])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('mode'))->toBe('link');
});

it('SYS-05 alamat server Wablas dan WAHA hanya https publik, bukan jaringan dalam', function () {
    siapWa();

    foreach (['http://tegal.wablas.com', 'https://localhost', 'https://127.0.0.1', 'https://10.0.0.5/api', 'https://192.168.1.10', 'https://169.254.169.254', 'https://server', 'https://mesin.internal', 'https://user:sandi@tegal.wablas.com', 'https://tegal.wablas.com/?a=1', 'ftp://tegal.wablas.com'] as $alamat) {
        simpanWa(['provider' => 'wablas', 'token' => 'tk', 'base_url' => $alamat])->assertSessionHas('error_code', 'reminder.invalid');
    }
    expect(DB::table('reminder_settings')->count())->toBe(0);

    simpanWa(['provider' => 'wablas', 'token' => 'tk', 'base_url' => 'https://Tegal.Wablas.com/'])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('api_base_url'))->toBe('https://tegal.wablas.com');
    simpanWa(['provider' => 'waha', 'base_url' => 'https://waha.klinik.id:3000/waha/', 'session' => 'ruang-1'])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('api_base_url'))->toBe('https://waha.klinik.id:3000/waha');
});

it('SYS-05 penjaga alamat menolak alamat IP khusus dan menerima nama server publik', function () {
    foreach (['https://8.8.8.8', 'https://93.184.216.34', 'https://waha.klinik.id', 'https://waha.klinik.id:8443/base'] as $ok) {
        expect(EndpointGuard::normalize($ok))->toStartWith('https://');
    }
    foreach (['https://0.0.0.0', 'https://[::1]', 'https://172.16.0.1', 'https://100.64.0.1/x', 'https://192.0.2.7', 'https://198.18.0.1', 'https://[::ffff:127.0.0.1]', 'https://[fc00::1]', 'https://rumah.lan'] as $buruk) {
        expect(fn () => EndpointGuard::normalize($buruk))->toThrow(InvalidReminder::class);
    }
});

it('SYS-05 isian yang tidak dipakai penyedia terpilih tidak disimpan', function () {
    siapWa();

    simpanWa(['provider' => 'fonnte', 'token' => 'tk', 'phone_number_id' => '999', 'base_url' => 'https://tegal.wablas.com', 'session' => 'x', 'staff_template' => 'staf'])->assertSessionHasNoErrors();

    $baris = DB::table('reminder_settings')->first();
    expect($baris->api_phone_number_id)->toBeNull()->and($baris->api_base_url)->toBeNull()->and($baris->api_session)->toBeNull()->and($baris->staff_template)->toBeNull();
});

it('SYS-05 pesan uji lewat Fonnte mengirim teks dengan token di header dan nomor internasional', function () {
    siapWa();
    simpanWa(['provider' => 'fonnte', 'token' => 'TOKEN-FONNTE'])->assertSessionHasNoErrors();
    fakeHttp(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['1']])]);

    $this->post('/settings/whatsapp/test', ['phone' => '0812-3456-7890'])->assertSessionHas('success');

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.fonnte.com/send' && $r->hasHeader('Authorization', 'TOKEN-FONNTE')
        && $r['target'] === '6281234567890' && str_contains((string) $r['message'], 'Pesan uji dari pengaturan WhatsApp SIKG'));
    expect(json_encode(DB::table('audit_logs')->where('action', 'whatsapp.test_sent')->get()))->not->toContain('81234567890')->not->toContain('TOKEN-FONNTE');
});

it('SYS-05 pesan uji lewat Wablas memanggil server akun klinik dan WAHA memakai sesi serta kunci API', function () {
    siapWa();
    resolverTetap();
    simpanWa(['provider' => 'wablas', 'token' => 'TOKEN.RAHASIA', 'base_url' => 'https://tegal.wablas.com'])->assertSessionHasNoErrors();
    fakeHttp(['tegal.wablas.com/*' => Http::response(['status' => true])]);

    $this->post('/settings/whatsapp/test', ['phone' => '+628123456789'])->assertSessionHas('success');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://tegal.wablas.com/api/send-message' && $r->hasHeader('Authorization', 'TOKEN.RAHASIA') && $r['phone'] === '628123456789');

    simpanWa(['provider' => 'waha', 'token' => 'KUNCI-API', 'base_url' => 'https://waha.klinik.id', 'session' => 'ruang-1'])->assertSessionHasNoErrors();
    fakeHttp(['waha.klinik.id/*' => Http::response(['id' => 'x'], 201)]);

    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('success');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://waha.klinik.id/api/sendText' && $r->hasHeader('X-Api-Key', 'KUNCI-API')
        && $r['chatId'] === '6281234567890@c.us' && $r['session'] === 'ruang-1' && str_contains((string) $r['text'], 'Pesan uji'));

    simpanWa(['provider' => 'waha', 'base_url' => 'https://waha.klinik.id'])->assertSessionHasNoErrors();
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('success');
    Http::assertSent(fn (Request $r) => $r->url() === 'https://waha.klinik.id/api/sendText' && $r['session'] === 'default');
});

it('SYS-05 server yang namanya mengarah ke jaringan dalam ditolak saat mengirim, tanpa memanggilnya', function () {
    siapWa();
    simpanWa(['provider' => 'wablas', 'token' => 'tk', 'base_url' => 'https://tegal.wablas.com'])->assertSessionHasNoErrors();
    fakeHttp();

    resolverTetap(['10.0.0.8']);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak dapat dijangkau'));
    resolverTetap(['93.184.216.34', '127.0.0.1']);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak dapat dijangkau'));
    resolverTetap([]);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak dapat dijangkau'));

    Http::assertNothingSent();
});

it('SYS-05 pesan uji WhatsApp Resmi memakai templat staf dengan dua parameter', function () {
    siapWa();
    simpanWa(['provider' => 'meta', 'phone_number_id' => '109876', 'token' => 'token-meta', 'staff_template' => 'notifikasi_staf'])->assertSessionHasNoErrors();
    fakeHttp(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('success');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/109876/messages') && $r->hasHeader('Authorization', 'Bearer token-meta')
        && $r['to'] === '6281234567890' && $r['template']['name'] === 'notifikasi_staf'
        && array_column($r['template']['components'][0]['parameters'], 'text')[0] === 'Uji SIKG');

    simpanWa(['provider' => 'meta', 'phone_number_id' => '109876', 'staff_template' => ''])->assertSessionHasNoErrors();
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'templat'));
});

it('SYS-05 pesan uji menolak nomor tidak sah, saluran belum siap, dan kegagalan penyedia tidak membocorkan nomor atau token', function () {
    siapWa();
    fakeHttp();

    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'belum lengkap'));
    simpanWa(['provider' => 'fonnte', 'token' => 'TOKEN-FONNTE'])->assertSessionHasNoErrors();
    $this->post('/settings/whatsapp/test', ['phone' => '+1 202 555 0100'])->assertSessionHas('error', fn ($m) => str_contains($m, 'nomor Indonesia'));
    $this->post('/settings/whatsapp/test', ['phone' => ''])->assertSessionHasErrors('phone');
    $this->post('/settings/whatsapp/test', ['phone' => '081200000000'])->assertSessionHas('error', fn ($m) => str_contains($m, 'nomor WhatsApp staf'));
    Http::assertNothingSent();

    fakeHttp(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'token invalid 6281234567890 TOKEN-FONNTE'], 200)]);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Fonnte menolak') && ! str_contains($m, '6281234567890') && ! str_contains($m, 'TOKEN-FONNTE'));

    fakeHttp(['api.fonnte.com/*' => fn () => throw new ConnectionException('gagal https://api.fonnte.com TOKEN-FONNTE')]);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => ! str_contains($m, 'TOKEN-FONNTE'));
});

it('SYS-05 rute pesan uji dibatasi 10 permintaan per menit dan dilindungi izin pengaturan', function () {
    $rute = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'settings/whatsapp/test');

    expect($rute)->not->toBeNull()->and($rute->gatherMiddleware())->toContain('throttle:10,1')->toContain('can:setting.manage');
});

it('SYS-05 pemberitahuan staf lewat penyedia tidak resmi berupa teks, lewat WhatsApp Resmi berupa templat, dan tanpa templat tidak dikirim', function () {
    siapWa();
    $staf = app(ChannelStaffWhatsApp::class);

    expect($staf->configured())->toBeFalse()->and($staf->send('628123456789', 'Judul', 'Isi'))->toBeFalse();

    simpanWa(['provider' => 'fonnte', 'token' => 'tk'])->assertSessionHasNoErrors();
    fakeHttp(['api.fonnte.com/*' => Http::response(['status' => true])]);
    expect($staf->configured())->toBeTrue()->and($staf->send('628123456789', 'Stok menipis', '3 obat di bawah minimum'))->toBeTrue();
    Http::assertSent(fn (Request $r) => $r['target'] === '628123456789' && $r['message'] === "[SIKG] Stok menipis\n3 obat di bawah minimum");

    simpanWa(['provider' => 'meta', 'phone_number_id' => '1', 'token' => 'tk'])->assertSessionHasNoErrors();
    expect($staf->configured())->toBeFalse();

    simpanWa(['provider' => 'meta', 'phone_number_id' => '1', 'staff_template' => 'notifikasi_staf'])->assertSessionHasNoErrors();
    fakeHttp(['graph.facebook.com/*' => Http::response(['messages' => []])]);
    expect($staf->configured())->toBeTrue()->and($staf->send('628123456789', 'Stok menipis', 'Isi'))->toBeTrue();
    Http::assertSent(fn (Request $r) => $r['template']['name'] === 'notifikasi_staf' && array_column($r['template']['components'][0]['parameters'], 'text') === ['Stok menipis', 'Isi']);
});

it('SYS-05 kegagalan pengiriman staf tidak melempar dan log tidak memuat nomor atau isi', function () {
    siapWa();
    simpanWa(['provider' => 'fonnte', 'token' => 'TOKEN-RAHASIA'])->assertSessionHasNoErrors();
    fakeHttp(['api.fonnte.com/*' => Http::response('', 503)]);
    Log::spy();

    expect(app(ChannelStaffWhatsApp::class)->send('628123456789', 'Judul', 'Isi rahasia'))->toBeFalse();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context = []) => ! str_contains(json_encode([$message, $context]), '628123456789') && ! str_contains(json_encode([$message, $context]), 'TOKEN-RAHASIA'));
});

it('P3-01 kode verifikasi booking lewat penyedia tidak resmi berupa teks berisi nama klinik, dan lewat WhatsApp Resmi berupa templat', function () {
    $s = siapWa();
    $pengirim = app(WhatsAppBookingCodeSender::class);
    expect($pengirim->available())->toBeFalse();
    expect(fn () => $pengirim->send('628123456789', '123456', 'kode_booking'))->toThrow(BookingCodeNotSent::class);

    simpanWa(['provider' => 'fonnte', 'token' => 'tk'])->assertSessionHasNoErrors();
    fakeHttp(['api.fonnte.com/*' => Http::response(['status' => true])]);
    expect($pengirim->available())->toBeTrue();
    $pengirim->send('628123456789', '123456', 'kode_booking');
    Http::assertSent(fn (Request $r) => str_contains((string) $r['message'], '123456') && str_contains((string) $r['message'], $s['klinik']->name));

    simpanWa(['provider' => 'meta', 'phone_number_id' => '1', 'token' => 'tk'])->assertSessionHasNoErrors();
    fakeHttp(['graph.facebook.com/*' => Http::response(['messages' => []])]);
    $pengirim->send('628123456789', '654321', 'kode_booking');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com') && $r['template']['name'] === 'kode_booking' && $r['template']['components'][0]['parameters'][0]['text'] === '654321');

    fakeHttp(['graph.facebook.com/*' => Http::response('', 503)]);
    expect(fn () => $pengirim->send('628123456789', '1', 'kode_booking'))->toThrow(BookingCodeNotSent::class);
});

it('SYS-05 nomor WhatsApp dinormalkan ke bentuk internasional dan nomor asing ditolak', function () {
    expect(WhatsAppNumber::tryFrom('0812-3456-7890')?->value)->toBe('6281234567890')
        ->and(WhatsAppNumber::tryFrom('+62 812 3456 7890')?->value)->toBe('6281234567890')
        ->and(WhatsAppNumber::tryFrom('81234567890')?->value)->toBe('6281234567890')
        ->and(WhatsAppNumber::tryFrom('+1 202 555 0100'))->toBeNull()
        ->and(WhatsAppNumber::tryFrom('bukan nomor'))->toBeNull()
        ->and(WhatsAppNumber::tryFrom('0812-3456-7890')?->masked())->toBe('0812****7890');
});

it('SYS-05 token tidak terbawa saat penyedia atau alamat server berganti', function () {
    siapWa();
    resolverTetap();
    simpanWa(['provider' => 'fonnte', 'token' => 'TOKEN-FONNTE'])->assertSessionHasNoErrors();

    // Pindah ke penyedia yang mewajibkan token: token lama tidak dihitung.
    simpanWa(['provider' => 'wablas', 'base_url' => 'https://tegal.wablas.com'])->assertSessionHas('error_code', 'reminder.invalid');
    expect(DB::table('reminder_settings')->value('provider'))->toBe('fonnte');

    // Pindah ke penyedia dengan token opsional: token lama dihapus, bukan dikirim ke server baru.
    simpanWa(['provider' => 'waha', 'base_url' => 'https://waha.klinik.id'])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('api_token_encrypted'))->toBeNull();
    fakeHttp(['waha.klinik.id/*' => Http::response(['id' => 'x'], 201)]);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('success');
    Http::assertSent(fn (Request $r) => ! $r->hasHeader('X-Api-Key') && ! $r->hasHeader('Authorization'));

    // Penyedia sama tetapi alamat server berganti: token wajib diisi ulang.
    simpanWa(['provider' => 'wablas', 'token' => 'TOKEN-WABLAS', 'base_url' => 'https://tegal.wablas.com'])->assertSessionHasNoErrors();
    simpanWa(['provider' => 'wablas', 'base_url' => 'https://penyerang.example.com'])->assertSessionHas('error_code', 'reminder.invalid');
    expect(DB::table('reminder_settings')->value('api_base_url'))->toBe('https://tegal.wablas.com');
    // Penyedia dan alamat sama, token kosong: token lama dipertahankan.
    $lama = DB::table('reminder_settings')->value('api_token_encrypted');
    simpanWa(['provider' => 'wablas', 'base_url' => 'https://tegal.wablas.com/'])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('api_token_encrypted'))->toBe($lama);
    // Pindah ke tautan manual menghapus token.
    simpanWa(['provider' => 'link'])->assertSessionHasNoErrors();
    expect(DB::table('reminder_settings')->value('api_token_encrypted'))->toBeNull();
});

it('SYS-05 audit pengaturan WhatsApp mencatat aktor serta isian bukan rahasia sebelum dan sesudah, tanpa token', function () {
    $s = siapWa();
    resolverTetap();

    simpanWa(['provider' => 'wablas', 'token' => 'TOKEN-RAHASIA', 'base_url' => 'https://tegal.wablas.com'])->assertSessionHasNoErrors();
    simpanWa(['provider' => 'waha', 'base_url' => 'https://waha.klinik.id', 'session' => 'ruang-1'])->assertSessionHasNoErrors();

    $log = DB::table('audit_logs')->where('action', 'whatsapp.settings_updated')->orderBy('id')->get();
    expect($log)->toHaveCount(2)->and($log[1]->actor_id)->toBe($s['admin']->id);
    $sebelum = json_decode($log[1]->before, true);
    $sesudah = json_decode($log[1]->after, true);
    expect($sebelum['provider'])->toBe('wablas')->and($sebelum['base_url'])->toBe('https://tegal.wablas.com')
        ->and($sesudah['provider'])->toBe('waha')->and($sesudah['base_url'])->toBe('https://waha.klinik.id')->and($sesudah['session'])->toBe('ruang-1')
        ->and(json_encode($log))->not->toContain('TOKEN-RAHASIA');

    fakeHttp(['waha.klinik.id/*' => Http::response(['id' => 'x'], 201)]);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('success');
    $uji = DB::table('audit_logs')->where('action', 'whatsapp.test_sent')->first();
    expect($uji->actor_id)->toBe($s['admin']->id)->and(json_decode($uji->after, true))->toBe(['ok' => true, 'to' => '0812****7890'])
        ->and(json_encode($uji))->not->toContain('081234567890')->not->toContain('6281234567890');
});

it('SYS-05 token dengan karakter kontrol atau spasi ditolak dan galat tak terduga klien menjadi kegagalan terkendali', function () {
    siapWa();

    simpanWa(['provider' => 'fonnte', 'token' => "abc\r\nX-Header: 1"])->assertSessionHasErrors('token');
    simpanWa(['provider' => 'fonnte', 'token' => 'ada spasi'])->assertSessionHasErrors('token');
    expect(DB::table('reminder_settings')->count())->toBe(0);

    simpanWa(['provider' => 'fonnte', 'token' => 'TOKEN-FONNTE'])->assertSessionHasNoErrors();
    fakeHttp(['api.fonnte.com/*' => fn () => throw new InvalidArgumentException('galat klien tak terduga TOKEN-FONNTE')]);
    $this->post('/settings/whatsapp/test', ['phone' => '081234567890'])->assertSessionHas('error', fn ($m) => ! str_contains($m, 'TOKEN-FONNTE') && str_contains($m, 'tidak dapat dikirim'));
});

it('SYS-05 penjaga alamat menolak IPv4 tidak kanonik, literal IPv6, karakter aneh, dan pembungkus IPv6 ke alamat dalam', function () {
    foreach (['https://127.1', 'https://2130706433', 'https://0x7f.0.0.1', 'https://[::1]', 'https://evil.com\\.127.0.0.1', 'https://a_b.example.com', 'https://224.0.0.1', 'https://1.2.3.4.5'] as $buruk) {
        expect(fn () => EndpointGuard::normalize($buruk))->toThrow(InvalidReminder::class);
    }
    foreach (['::1', 'fc00::1', 'fe80::1', 'ff02::1', '::ffff:127.0.0.1', '::ffff:7f00:1', '::ffff:a00:1', '::127.0.0.1', '64:ff9b::7f00:1', '2002:7f00:1::', '100.64.0.1', '224.0.0.1', '2001:db8::1'] as $ip) {
        expect(EndpointGuard::publicIp($ip))->toBeFalse($ip);
    }
    foreach (['8.8.8.8', '93.184.216.34', '2606:4700::1111'] as $ip) {
        expect(EndpointGuard::publicIp($ip))->toBeTrue($ip);
    }
});
