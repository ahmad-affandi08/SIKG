<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\Notifications\Notification;
use Src\Shared\Application\Notifications\Notifier;
use Src\Shared\Application\Notifications\StaffAlerts;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function notif(string $kunci = 'uji:1', string $izin = 'stock.manage', string $judul = 'Stok menipis'): Notification
{
    return new Notification('stock.low', $judul, 'Obat Uji tersisa 3 tablet (minimum 10).', '/stock', $izin, $kunci);
}

it('SYS-05 pemberitahuan terbit sekali per kunci dan tidak ditulis ulang', function () {
    $k = makeClinic();
    tenant()->set($k->id);

    expect(app(Notifier::class)->publish(notif('stok:2025-06-16:A')))->toBeTrue()
        ->and(app(Notifier::class)->publish(notif('stok:2025-06-16:A')))->toBeFalse()
        ->and(app(Notifier::class)->publish(notif('stok:2025-06-16:B')))->toBeTrue()
        ->and(DB::table('notifications')->count())->toBe(2);
});

it('SYS-05 pengguna hanya melihat pemberitahuan yang izinnya ia miliki', function () {
    $k = makeClinic();
    $perawat = makeUser(Role::Perawat, $k);
    $resepsionis = makeUser(Role::Resepsionis, $k);
    tenant()->set($k->id);
    app(Notifier::class)->publish(notif('stok:1', 'stock.manage'));
    app(Notifier::class)->publish(notif('umum:1', 'appointment.view', 'Janji baru'));

    $this->actingAs($perawat)->getJson('/internal-api/notifications')->assertOk()
        ->assertJsonPath('data.unread', 2)->assertJsonCount(2, 'data.items');
    $this->actingAs($resepsionis)->getJson('/internal-api/notifications')->assertOk()
        ->assertJsonPath('data.unread', 1)->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.title', 'Janji baru');
});

it('SYS-05 status dibaca disimpan per pengguna dan tandai semua dibaca', function () {
    $k = makeClinic();
    $a = makeUser(Role::Perawat, $k);
    $b = makeUser(Role::Perawat, $k);
    tenant()->set($k->id);
    app(Notifier::class)->publish(notif('stok:1'));
    app(Notifier::class)->publish(notif('stok:2'));
    $id = DB::table('notifications')->orderBy('id')->value('id');

    $this->actingAs($a)->postJson("/internal-api/notifications/{$id}/read")->assertOk()->assertJsonPath('data.unread', 1);
    $this->actingAs($b)->getJson('/internal-api/notifications')->assertJsonPath('data.unread', 2);
    $this->actingAs($a)->postJson('/internal-api/notifications/read-all')->assertOk()->assertJsonPath('data.unread', 0);
    $this->actingAs($b)->getJson('/internal-api/notifications')->assertJsonPath('data.unread', 2);
    expect(DB::table('notification_reads')->where('user_id', $a->id)->count())->toBe(2)->and(DB::table('notification_reads')->where('user_id', $b->id)->count())->toBe(0);
});

it('SYS-05 tidak dapat menandai dibaca pemberitahuan yang bukan miliknya atau milik klinik lain', function () {
    $k = makeClinic();
    $lain = makeClinic('Klinik Lain');
    $resepsionis = makeUser(Role::Resepsionis, $k);
    tenant()->set($k->id);
    app(Notifier::class)->publish(notif('stok:1', 'stock.manage'));
    tenant()->set($lain->id);
    app(Notifier::class)->publish(notif('lain:1', 'appointment.view'));
    $stok = DB::table('notifications')->where('dedupe_key', 'stok:1')->value('id');
    $milikLain = DB::table('notifications')->where('dedupe_key', 'lain:1')->value('id');

    tenant()->set($k->id);
    $this->actingAs($resepsionis)->postJson("/internal-api/notifications/{$stok}/read")->assertOk();
    $this->actingAs($resepsionis)->postJson("/internal-api/notifications/{$milikLain}/read")->assertOk();

    expect(DB::table('notification_reads')->count())->toBe(0);
    $this->actingAs($resepsionis)->getJson('/internal-api/notifications')->assertJsonPath('data.unread', 0)->assertJsonCount(0, 'data.items');
});

it('SYS-05 halaman notifikasi dan polling menuntut login', function () {
    $this->get('/notifications')->assertRedirect('/login');
    $this->getJson('/internal-api/notifications')->assertUnauthorized();
});

it('SYS-05 halaman notifikasi memuat daftar untuk pengguna', function () {
    $k = makeClinic();
    $perawat = makeUser(Role::Perawat, $k);
    tenant()->set($k->id);
    app(Notifier::class)->publish(notif('stok:1'));

    $this->actingAs($perawat)->get('/notifications')->assertOk()->assertInertia(fn ($p) => $p->component('Notifications/Index')->where('unread', 1)->has('items', 1));
});

it('SYS-05 peringatan diteruskan ke WhatsApp staf klinik hanya saat pertama terbit', function () {
    $k = makeClinic();
    tenant()->set($k->id);
    DB::table('clinics')->where('id', $k->id)->update(['settings' => json_encode(['notify_whatsapp' => ['628123450001', '628123450002']])]);
    rekamKirim();

    $baru = app(StaffAlerts::class)->raise(notif('stok:1'));
    $ulang = app(StaffAlerts::class)->raise(notif('stok:1'));

    expect($baru)->toBeTrue()->and($ulang)->toBeFalse()
        ->and(KirimPerekam::$whatsapp)->toHaveCount(2)->and(KirimPerekam::$whatsapp[0]['phone'])->toBe('628123450001')->and(KirimPerekam::$whatsapp[1]['phone'])->toBe('628123450002')
        ->and(KirimPerekam::$whatsapp[0]['title'])->toBe('Stok menipis');
});

it('SYS-05 saluran yang belum dikonfigurasi atau gagal tidak menggagalkan peringatan dalam aplikasi', function () {
    $k = makeClinic();
    tenant()->set($k->id);
    DB::table('clinics')->where('id', $k->id)->update(['settings' => json_encode(['notify_whatsapp' => ['628123450001', '628123450002']])]);

    rekamKirim(whatsapp: false);
    expect(app(StaffAlerts::class)->raise(notif('stok:1')))->toBeTrue()->and(KirimPerekam::$whatsapp)->toBe([]);

    rekamKirim();
    KirimPerekam::$gagal = true;
    expect(app(StaffAlerts::class)->raise(notif('stok:2')))->toBeTrue()->and(DB::table('notifications')->count())->toBe(2);
});

it('SYS-05 tanpa tujuan di pengaturan tidak ada yang dikirim keluar', function () {
    $k = makeClinic();
    tenant()->set($k->id);
    rekamKirim();

    app(StaffAlerts::class)->raise(notif('stok:1'));

    expect(KirimPerekam::$whatsapp)->toBe([])->and(DB::table('notifications')->count())->toBe(1);
});

it('SYS-05 janji baru menjadi pemberitahuan tanpa nama pasien dan diteruskan ke tujuan klinik', function () {
    $s = siapJadwal();
    tenant()->set($s['klinik']->id);
    DB::table('clinics')->where('id', $s['klinik']->id)->update(['settings' => json_encode(['notify_whatsapp' => ['628123450001', '628123450002']])]);
    rekamKirim();

    $janji = bukaJanji($s);

    $n = DB::table('notifications')->where('dedupe_key', "appointment.booked:{$janji}")->first();
    expect($n)->not->toBeNull()->and($n->kind)->toBe('appointment.booked')->and($n->permission)->toBe('appointment.view')
        ->and($n->body)->toContain('16/06/2025 09:00')->and($n->body)->toContain('drg. Sari')->and($n->body)->not->toContain('Budi')
        ->and($n->link)->toBe('/schedule?date=2025-06-16')
        ->and(KirimPerekam::$whatsapp)->toHaveCount(2)->and(KirimPerekam::$whatsapp[0]['body'])->not->toContain('Budi');
});

it('SYS-05 pengaturan klinik menyimpan nomor WhatsApp staf dan menolak isian tidak sah', function () {
    $admin = makeUser(Role::Admin);
    $jam = ['open' => '08:00', 'close' => '17:00'];
    $payload = [
        'timezone' => 'Asia/Jakarta', 'session_timeout_minutes' => 30, 'medical_record_pattern' => 'RM-{seq:6}', 'invoice_pattern' => 'INV-{yyyy}-{seq:5}',
        'discount_limits' => ['pemilik' => 100, 'admin' => 100, 'kasir' => 10], 'tax_percent' => 0,
        'operating_hours' => [1 => $jam, 2 => $jam, 3 => $jam, 4 => $jam, 5 => $jam, 6 => $jam, 7 => null],
    ];

    $this->actingAs($admin)->put('/settings/clinic', [...$payload, 'notify_whatsapp' => ['0812-3450-0001', '+62 812 3450 0002', '081234500001'], 'reminders_enabled' => false])->assertSessionHasNoErrors();
    $this->actingAs($admin)->get('/settings/clinic')->assertInertia(fn ($p) => $p->where('settings.notify_whatsapp', ['6281234500001', '6281234500002'])->where('settings.reminders_enabled', false));

    $this->actingAs($admin)->put('/settings/clinic', [...$payload, 'notify_whatsapp' => ['bukan-nomor']])->assertSessionHasErrors('settings');
    $this->actingAs($admin)->put('/settings/clinic', [...$payload, 'notify_whatsapp' => ['+1 202 555 0100']])->assertSessionHasErrors('settings');
    $this->actingAs($admin)->put('/settings/clinic', [...$payload, 'notify_whatsapp' => array_fill(0, 6, '081234500001')])->assertSessionHasErrors('notify_whatsapp');
});

it('SYS-05 perintah pangkas membuang pemberitahuan lebih tua dari 90 hari saja', function () {
    $k = makeClinic();
    $u = makeUser(Role::Perawat, $k);
    tenant()->set($k->id);
    app(Notifier::class)->publish(notif('lama'));
    app(Notifier::class)->publish(notif('baru'));
    DB::table('notifications')->where('dedupe_key', 'lama')->update(['created_at' => Carbon::now()->subDays(91)]);
    $lama = DB::table('notifications')->where('dedupe_key', 'lama')->value('id');
    DB::table('notification_reads')->insert(['notification_id' => $lama, 'user_id' => $u->id, 'read_at' => Carbon::now()->subDays(90)]);

    $this->artisan('sikg:notifications:prune')->assertSuccessful();

    expect(DB::table('notifications')->pluck('dedupe_key')->all())->toBe(['baru'])->and(DB::table('notification_reads')->count())->toBe(0);
});
