<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Clinic\Contracts\ServiceProfileLookup;
use Src\Clinic\Domain\ClinicSettings;
use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Domain\ServiceProfile;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function jadikanProfil(ClinicModel $klinik, ServiceProfile $profil): void
{
    $klinik->forceFill(['settings' => $klinik->clinicSettings()->withServiceProfile($profil)->toArray()])->save();
}

it('KLU-01 klinik yang ada tetap berprofil gigi dan data lama tanpa kunci profil terbaca sebagai gigi', function () {
    expect(ClinicSettings::fromArray(null)->serviceProfile)->toBe(ServiceProfile::Dental)
        ->and(ClinicSettings::fromArray(['tax_percent' => 0])->serviceProfile)->toBe(ServiceProfile::Dental)
        ->and(ClinicSettings::fromArray(['service_profile' => 'tidak-dikenal'])->serviceProfile)->toBe(ServiceProfile::Dental)
        ->and(ClinicSettings::fromArray(['service_profile' => 'general'])->serviceProfile)->toBe(ServiceProfile::General);

    $admin = makeUser(Role::Admin);
    $this->actingAs($admin);
    expect(app(ServiceProfileLookup::class)->current())->toBe(ServiceProfile::Dental);
});

it('KLU-01 Admin melihat pilihan profil dan mengganti ke klinik umum dengan audit sebelum dan sesudah', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->get('/settings/service-profile')->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/ServiceProfile')->where('current', 'dental')
            ->has('options', 2)->where('options.0.value', 'dental')->where('options.1.value', 'general'));

    $this->put('/settings/service-profile', ['profile' => 'general'])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($klinik->refresh()->clinicSettings()->serviceProfile)->toBe(ServiceProfile::General);
    tenant()->set($klinik->id);
    $log = AuditLogModel::query()->where('action', 'clinic.service_profile_changed')->firstOrFail();
    expect($log->actor_id)->toBe($admin->id)->and($log->before['service_profile'])->toBe('dental')->and($log->after['service_profile'])->toBe('general');

    // Memilih profil yang sama tidak membuat audit baru.
    $this->put('/settings/service-profile', ['profile' => 'general'])->assertSessionHasNoErrors();
    tenant()->set($klinik->id);
    expect(AuditLogModel::query()->where('action', 'clinic.service_profile_changed')->count())->toBe(1);
});

it('KLU-01 profil tidak dikenal ditolak dan peran tanpa izin pengaturan tidak dapat membuka atau mengubahnya', function () {
    $klinik = makeClinic();
    $this->actingAs(makeUser(Role::Admin, $klinik))->put('/settings/service-profile', ['profile' => 'rumah-sakit'])->assertSessionHasErrors('profile');
    $this->put('/settings/service-profile', [])->assertSessionHasErrors('profile');

    foreach ([Role::Resepsionis, Role::Kasir, Role::Perawat, Role::Dokter] as $peran) {
        $this->actingAs(makeUser($peran, $klinik))->get('/settings/service-profile')->assertForbidden();
        $this->put('/settings/service-profile', ['profile' => 'general'])->assertForbidden();
    }
    expect($klinik->refresh()->clinicSettings()->serviceProfile)->toBe(ServiceProfile::Dental);
});

it('KLU-01 menyimpan pengaturan klinik umum tidak menimpa profil layanan', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $jam = ['open' => '08:00', 'close' => '17:00'];
    $this->actingAs($admin)->put('/settings/service-profile', ['profile' => 'general'])->assertSessionHasNoErrors();

    $this->put('/settings/clinic', [
        'timezone' => 'Asia/Jakarta', 'session_timeout_minutes' => 30, 'medical_record_pattern' => 'RM-{seq:6}', 'invoice_pattern' => 'INV-{yyyy}-{seq:5}',
        'discount_limits' => ['pemilik' => 100, 'admin' => 100, 'kasir' => 10], 'tax_percent' => 0,
        'operating_hours' => [1 => $jam, 2 => $jam, 3 => $jam, 4 => $jam, 5 => $jam, 6 => $jam, 7 => null],
    ])->assertSessionHasNoErrors();

    expect($klinik->refresh()->clinicSettings()->serviceProfile)->toBe(ServiceProfile::General);
});

it('KLU-01 server menutup fitur gigi pada klinik umum (404) walau pengguna berizin, dan membukanya pada klinik gigi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin);
    $ulid = str_repeat('0', 26);
    $tutup = [
        ['get', '/orthodontics'], ['get', '/orthodontics/create'], ['get', "/orthodontics/cases/{$ulid}"], ['post', '/orthodontics'],
        ['get', '/lab-orders'], ['get', '/lab-orders/create'], ['post', '/lab-orders'], ['get', "/lab-orders/{$ulid}"],
        ['get', "/patients/{$ulid}/odontogram/compare"], ['get', "/patients/{$ulid}/odontogram/print"],
        ['get', "/internal-api/patients/{$ulid}/odontogram"], ['put', "/internal-api/encounters/{$ulid}/odontogram/teeth/11"],
        ['post', "/encounters/{$ulid}/procedures/{$ulid}/odontogram"],
    ];

    jadikanProfil($klinik, ServiceProfile::General);
    foreach ($tutup as [$metode, $alamat]) {
        $this->{$metode}($alamat)->assertNotFound();
    }

    // Pada klinik gigi route yang sama tidak lagi 404 (halaman terbuka, atau ditolak aturan lain), jadi penutupnya khusus profil.
    jadikanProfil($klinik, ServiceProfile::Dental);
    $this->get('/orthodontics')->assertOk();
    $this->get('/lab-orders')->assertOk();
});

it('KLU-02 klinik umum mendapat prop bersama tanpa fitur gigi dan sebutan Dokter, klinik gigi tetap Dokter gigi', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->get('/dashboard')->assertInertia(fn ($p) => $p->where('clinic.profile', 'dental')->where('clinic.dental', true)->where('clinic.doctor_title', 'Dokter gigi'));

    jadikanProfil($klinik, ServiceProfile::General);
    $this->get('/dashboard')->assertInertia(fn ($p) => $p->where('clinic.profile', 'general')->where('clinic.dental', false)->where('clinic.doctor_title', 'Dokter'));
});

it('KLU-02 label peran Dokter mengikuti profil layanan di halaman peran dan daftar peran pengguna', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->get('/settings/roles')->assertInertia(fn ($p) => $p->where('roles.3.value', 'dokter')->where('roles.3.label', 'Dokter gigi'));
    $this->get('/settings/users/create')->assertInertia(fn ($p) => $p->where('roles', fn ($roles) => collect($roles)->firstWhere('value', 'dokter')['label'] === 'Dokter gigi'));

    jadikanProfil($klinik, ServiceProfile::General);
    $this->get('/settings/roles')->assertInertia(fn ($p) => $p->where('roles.3.label', 'Dokter'));
    $this->get('/settings/users/create')->assertInertia(fn ($p) => $p->where('roles', fn ($roles) => collect($roles)->firstWhere('value', 'dokter')['label'] === 'Dokter'));
});

it('KLU-01 mengganti profil tidak menghapus data odontogram, ortodonti, atau lab yang sudah ada', function () {
    $s = siapKunjungan();
    $tabel = ['odontogram_snapshots', 'orthodontic_cases', 'lab_orders', 'encounters'];
    $sebelum = array_map(fn (string $t) => DB::table($t)->count(), $tabel);

    $this->actingAs($s['user']);
    $klinik = ClinicModel::query()->findOrFail($s['klinik']->id);
    jadikanProfil($klinik, ServiceProfile::General);
    jadikanProfil($klinik, ServiceProfile::Dental);

    expect(array_map(fn (string $t) => DB::table($t)->count(), $tabel))->toBe($sebelum);
});

it('KLU-01 setiap route odontogram, ortodonti, dan lab memuat penutup profil gigi', function () {
    $wajib = [];
    foreach (app('router')->getRoutes()->getRoutes() as $rute) {
        $aksi = $rute->getActionName();
        $gigi = str_contains($aksi, 'Src\\Orthodontics\\') || str_contains($aksi, 'Src\\Lab\\') || str_contains($aksi, 'Odontogram')
            || str_contains($aksi, '@resolveOdontogram');
        if ($gigi) {
            $wajib[] = $rute->uri();
            expect(in_array('profile.dental', $rute->gatherMiddleware(), true))->toBeTrue("{$rute->uri()} belum memakai profile.dental");
        }
    }

    expect(count($wajib))->toBeGreaterThan(15);
});

it('KLU-02 halaman pemeriksaan klinik umum tidak membuka izin odontogram dan tanpa usulan odontogram', function () {
    $s = siapKunjungan();
    $this->actingAs($s['dokter_user'])->get("/encounters/{$s['kunjungan']}")->assertOk()
        ->assertInertia(fn ($p) => $p->where('can.odontogram', true));

    tenant()->set($s['klinik']->id);
    $klinik = ClinicModel::query()->findOrFail($s['klinik']->id);
    jadikanProfil($klinik, ServiceProfile::General);

    $this->get("/encounters/{$s['kunjungan']}")->assertOk()
        ->assertInertia(fn ($p) => $p->where('can.odontogram', false)->where('odontogram_suggestions', []));
});
