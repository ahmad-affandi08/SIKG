<?php

declare(strict_types=1);

use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EnvironmentInspector;
use Src\Shared\Application\HealthMonitor;
use Src\Shared\Application\PreflightCheck;
use Src\Shared\Infrastructure\FrozenClock;
use Src\Shared\Infrastructure\Health\DatabaseHealthMonitor;

it('SYS-06 pemeriksaan kesiapan memuat butir wajib dan tidak membuka rahasia', function () {
    $checks = collect(app(EnvironmentInspector::class)->inspect());
    $keys = $checks->pluck('key')->all();

    expect($keys)->toContain('php_version', 'extensions', 'writable', 'upload_max_filesize', 'post_max_size', 'memory_limit',
        'drivers', 'app_debug', 'secrets', 'web_exposure', 'hosting_profile', 'database', 'heartbeat');

    $semua = json_encode($checks->map(fn (PreflightCheck $c) => [$c->label, $c->detail])->all());
    expect($semua)->not->toContain((string) config('app.key'))
        ->and($semua)->not->toContain((string) config('sikg.pii_hash_key'))
        ->and($semua)->not->toContain(config('database.connections.mysql.password') ?: 'tidak-ada-kata-sandi-db');
});

it('SYS-06 hasil memuat status valid dan butir inti lulus di lingkungan tes', function () {
    $checks = collect(app(EnvironmentInspector::class)->inspect())->keyBy('key');

    foreach ($checks as $check) {
        expect($check->status)->toBeIn([PreflightCheck::PASS, PreflightCheck::WARN, PreflightCheck::FAIL]);
    }
    expect($checks['php_version']->status)->toBe(PreflightCheck::PASS)
        ->and($checks['extensions']->status === PreflightCheck::PASS || str_starts_with($checks['extensions']->detail, 'Belum aktif: '))->toBeTrue()
        ->and($checks['writable']->status)->toBe(PreflightCheck::PASS)
        ->and($checks['secrets']->status)->toBe(PreflightCheck::PASS)
        ->and($checks['web_exposure']->status)->toBe(PreflightCheck::PASS)
        ->and($checks['database']->status)->toBe(PreflightCheck::PASS);
});

it('SYS-06 profil hosting tidak dikenal dan driver Redis di shared hosting dilaporkan gagal', function () {
    config(['sikg.hosting_profile' => 'awan']);
    $checks = collect(app(EnvironmentInspector::class)->inspect())->keyBy('key');
    expect($checks['hosting_profile']->status)->toBe(PreflightCheck::FAIL);

    config(['sikg.hosting_profile' => 'shared', 'cache.default' => 'redis']);
    $checks = collect(app(EnvironmentInspector::class)->inspect())->keyBy('key');
    expect($checks['drivers']->status)->toBe(PreflightCheck::FAIL)
        ->and($checks['drivers']->detail)->toContain('redis');
});

it('SYS-06 debug aktif di produksi dan kunci kosong dilaporkan gagal', function () {
    $this->app['env'] = 'production';
    config(['app.debug' => true, 'sikg.pii_hash_key' => '']);
    $checks = collect(app(EnvironmentInspector::class)->inspect())->keyBy('key');

    expect($checks['app_debug']->status)->toBe(PreflightCheck::FAIL)
        ->and($checks['secrets']->status)->toBe(PreflightCheck::FAIL)
        ->and($checks['secrets']->detail)->toContain('PII_HASH_KEY');
});

it('SYS-06 perintah sikg:preflight menampilkan hasil per butir', function () {
    $this->artisan('sikg:preflight')
        ->expectsOutputToContain('Versi PHP')
        ->expectsOutputToContain('Ekstensi PHP wajib')
        ->expectsOutputToContain('Denyut cron dan antrean');
});

it('SYS-06 halaman sistem hanya untuk Admin dan Pemilik', function () {
    $this->actingAs(makeUser(Role::Admin))->get('/settings/system')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/System')->has('checks')->has('health.status')->where('hostingProfile', 'shared'));
    $this->actingAs(makeUser(Role::Pemilik))->get('/settings/system')->assertOk();

    foreach ([Role::Resepsionis, Role::Dokter, Role::Perawat, Role::Kasir] as $peran) {
        $this->actingAs(makeUser($peran))->get('/settings/system')->assertForbidden();
    }
});

it('SYS-07 denyut tercatat dan umurnya dihitung dari jam sistem', function () {
    $clock = new FrozenClock('2030-01-01 10:00:00');
    $this->app->instance(Clock::class, $clock);
    $monitor = new DatabaseHealthMonitor($clock);

    expect($monitor->snapshot()->schedulerAgeSeconds)->toBeNull()
        ->and($monitor->snapshot()->status)->toBe('degraded');

    $monitor->beat('scheduler');
    $monitor->beat('queue');
    $clock->advance('PT90S');

    $snapshot = $monitor->snapshot();
    expect($snapshot->schedulerAgeSeconds)->toBe(90)
        ->and($snapshot->queueAgeSeconds)->toBe(90)
        ->and($snapshot->status)->toBe('ok');

    $clock->advance('PT20M');
    expect($monitor->snapshot()->status)->toBe('degraded');
});

it('SYS-07 job gagal membuat status menurun dan job tertunda dihitung', function () {
    $monitor = app(HealthMonitor::class);
    $monitor->beat('scheduler');
    $monitor->beat('queue');
    expect($monitor->snapshot()->status)->toBe('ok');

    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
    DB::table('failed_jobs')->insert(['uuid' => 'abc', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

    $snapshot = $monitor->snapshot();
    expect($snapshot->pendingJobs)->toBe(1)->and($snapshot->failedJobs)->toBe(1)->and($snapshot->status)->toBe('degraded');
});

it('SYS-07 scheduler terdaftar untuk berdenyut tiap menit dan memeriksa kesehatan', function () {
    $names = collect(Schedule::events())->map(fn ($e) => $e->description)->all();

    expect($names)->toContain('sikg-heartbeat-scheduler', 'sikg-health-check');
});

it('SYS-07 schedule:run menulis denyut scheduler', function () {
    $this->artisan('schedule:run')->assertSuccessful();

    expect(DB::table('system_heartbeats')->where('name', 'scheduler')->exists())->toBeTrue();
});

it('SYS-07 worker antrean menulis denyut saat memulai putaran', function () {
    event(new Looping('database', 'default'));

    expect(DB::table('system_heartbeats')->where('name', 'queue')->exists())->toBeTrue();
});

it('SYS-07 /health menolak tanpa token, token salah, dan bila token server kosong', function () {
    config(['sikg.health_token' => 'token-rahasia-uji']);
    $this->getJson('/health')->assertForbidden();
    $this->getJson('/health', ['X-Health-Token' => 'salah'])->assertForbidden();

    config(['sikg.health_token' => '']);
    $this->getJson('/health', ['X-Health-Token' => ''])->assertForbidden();
});

it('SYS-07 /health dengan token menampilkan umur denyut tanpa PHI; 503 saat menurun, 200 saat sehat', function () {
    config(['sikg.health_token' => 'token-rahasia-uji']);
    $pasien = 'Nama Pasien Rahasia';

    $this->getJson('/health', ['X-Health-Token' => 'token-rahasia-uji'])
        ->assertStatus(503)
        ->assertJsonStructure(['status', 'scheduler_age_seconds', 'queue_age_seconds', 'pending_jobs', 'failed_jobs', 'stale_after_seconds']);

    $monitor = app(HealthMonitor::class);
    $monitor->beat('scheduler');
    $monitor->beat('queue');

    $res = $this->getJson('/health', ['X-Health-Token' => 'token-rahasia-uji'])->assertOk()->assertJson(['status' => 'ok']);
    expect($res->getContent())->not->toContain($pasien);
});

it('SYS-07 /up tetap publik tanpa detail', function () {
    $this->get('/up')->assertOk();
});

it('SYS-07 peringatan WhatsApp dan notifikasi terbit sekali saat menurun, tidak berulang, dan pulih mengirim lagi saat menurun berikutnya', function () {
    $klinik = makeClinic();
    $klinik->forceFill(['settings' => ['notify_whatsapp' => ['628123450001']]])->save();
    rekamKirim();

    $this->artisan('sikg:health-check')->assertSuccessful();
    $this->artisan('sikg:health-check')->assertSuccessful();
    expect(KirimPerekam::$whatsapp)->toHaveCount(1)->and(KirimPerekam::$whatsapp[0]['title'])->toBe('Kesehatan sistem menurun')
        ->and(DB::table('notifications')->where('kind', 'system.health')->where('permission', 'setting.manage')->count())->toBe(1);

    $monitor = app(HealthMonitor::class);
    $monitor->beat('scheduler');
    $monitor->beat('queue');
    $this->artisan('sikg:health-check')->assertSuccessful();

    DB::table('failed_jobs')->insert(['uuid' => 'x', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
    $this->artisan('sikg:health-check')->assertSuccessful();
    expect(KirimPerekam::$whatsapp)->toHaveCount(2);
});

it('SYS-07 tanpa nomor WhatsApp staf peringatan hanya tampil di aplikasi', function () {
    makeClinic();
    rekamKirim();

    $this->artisan('sikg:health-check')->assertSuccessful();

    expect(KirimPerekam::$whatsapp)->toBe([])->and(DB::table('notifications')->where('kind', 'system.health')->count())->toBe(1);
});

it('SYS-07 perintah health-check mencatat peringatan tanpa PHI saat menurun', function () {
    Log::spy();

    $this->artisan('sikg:health-check')->assertSuccessful();

    Log::shouldHaveReceived('warning')->once();
});

it('SYS-07 keadaan sehat tidak mencatat peringatan', function () {
    $monitor = app(HealthMonitor::class);
    $monitor->beat('scheduler');
    $monitor->beat('queue');
    Log::spy();

    $this->artisan('sikg:health-check')->expectsOutput('Sehat.')->assertSuccessful();

    Log::shouldNotHaveReceived('warning');
});
