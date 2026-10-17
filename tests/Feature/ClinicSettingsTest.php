<?php

declare(strict_types=1);

use Src\Clinic\Infrastructure\Eloquent\ClinicModel;
use Src\Clinic\Infrastructure\Eloquent\HolidayModel;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

function payloadPengaturan(array $override = []): array
{
    $jam = ['open' => '08:00', 'close' => '17:00'];

    return [
        'timezone' => 'Asia/Makassar',
        'session_timeout_minutes' => 45,
        'medical_record_pattern' => 'RM-{seq:7}',
        'invoice_pattern' => 'FKT/{yyyy}/{seq:4}',
        'operating_hours' => [1 => $jam, 2 => $jam, 3 => $jam, 4 => $jam, 5 => $jam, 6 => ['open' => '08:00', 'close' => '12:00'], 7 => null],
        ...$override,
    ];
}

it('SYS-04 Admin melihat halaman pengaturan klinik', function () {
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->get('/settings/clinic')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Settings/Clinic')
            ->where('settings.session_timeout_minutes', 30)
            ->where('timezone', 'Asia/Jakarta')
            ->has('example.medicalRecordNo')
            ->where('sessionMaxMinutes', 480));
});

it('SYS-04 Admin menyimpan pengaturan dan perubahan tercatat di audit', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->put('/settings/clinic', payloadPengaturan())->assertSessionHasNoErrors()->assertRedirect();

    $klinik->refresh();
    $settings = $klinik->clinicSettings();
    expect($klinik->timezone)->toBe('Asia/Makassar')
        ->and($settings->sessionTimeoutMinutes)->toBe(45)
        ->and($settings->medicalRecordPattern->pattern)->toBe('RM-{seq:7}')
        ->and($settings->isOpenOn(7))->toBeFalse()
        ->and($settings->operatingHours[6])->toBe(['open' => '08:00', 'close' => '12:00']);

    tenant()->set($klinik->id);
    $log = AuditLogModel::query()->where('action', 'clinic.settings_updated')->firstOrFail();
    expect($log->actor_id)->toBe($admin->id)
        ->and($log->before['timezone'])->toBe('Asia/Jakarta')
        ->and($log->after['timezone'])->toBe('Asia/Makassar');
});

it('SYS-04 pengaturan tidak sah ditolak dengan pesan Indonesia', function (array $override, string $kolom) {
    $this->actingAs(makeUser(Role::Admin))->from('/settings/clinic')
        ->put('/settings/clinic', payloadPengaturan($override))
        ->assertSessionHasErrors($kolom);
})->with([
    'zona waktu' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'sesi terlalu pendek' => [['session_timeout_minutes' => 2], 'session_timeout_minutes'],
    'sesi melebihi batas server' => [['session_timeout_minutes' => 9999], 'session_timeout_minutes'],
    'jam salah format' => [['operating_hours' => [1 => ['open' => '8', 'close' => '17:00'], 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null]], 'operating_hours.1.open'],
]);

it('SYS-04 format nomor tanpa seq ditolak', function () {
    $this->actingAs(makeUser(Role::Admin))->from('/settings/clinic')
        ->put('/settings/clinic', payloadPengaturan(['medical_record_pattern' => 'RM-001']))
        ->assertSessionHasErrors('settings');
});

it('SYS-04 peran tanpa izin pengaturan ditolak', function (Role $peran) {
    $user = makeUser($peran);

    $this->actingAs($user)->get('/settings/clinic')->assertForbidden();
    $this->actingAs($user)->put('/settings/clinic', payloadPengaturan())->assertForbidden();
})->with([Role::Resepsionis, Role::Dokter, Role::Perawat, Role::Kasir]);

it('SYS-04 Pemilik juga dapat mengelola pengaturan', function () {
    $this->actingAs(makeUser(Role::Pemilik))->get('/settings/clinic')->assertOk();
});

it('SYS-04 hari libur ditambah, ganda ditolak, dan dihapus lewat soft delete dengan audit', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/holidays', ['date' => '2030-08-17', 'name' => 'Hari Kemerdekaan'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $libur = HolidayModel::query()->firstOrFail();
    expect($libur->name)->toBe('Hari Kemerdekaan');

    $this->actingAs($admin)->from('/settings/clinic')->post('/settings/holidays', ['date' => '2030-08-17', 'name' => 'Lagi'])
        ->assertSessionHasErrors(['domain']);

    $this->actingAs($admin)->delete("/settings/holidays/{$libur->id}")->assertRedirect();

    tenant()->set($klinik->id);
    expect(HolidayModel::query()->count())->toBe(0)
        ->and(HolidayModel::withTrashed()->count())->toBe(1)
        ->and(AuditLogModel::query()->whereIn('action', ['holiday.created', 'holiday.deleted'])->count())->toBe(2);
});

it('SYS-04 hari libur klinik lain tidak dapat dihapus (IDOR)', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $adminA = makeUser(Role::Admin, $a);
    tenant()->set($b->id);
    $liburB = new HolidayModel;
    $liburB->forceFill(['date' => '2030-01-01', 'name' => 'Tahun Baru'])->save();
    tenant()->clear();

    $this->actingAs($adminA)->delete("/settings/holidays/{$liburB->id}")->assertNotFound();

    expect(HolidayModel::withoutGlobalScopes()->count())->toBe(1);
});

it('SYS-04 validasi hari libur berbahasa Indonesia', function () {
    $this->actingAs(makeUser(Role::Admin))->from('/settings/clinic')->post('/settings/holidays', ['date' => '17-08-2030', 'name' => ''])
        ->assertSessionHasErrors(['date' => 'Tanggal harus berformat YYYY-MM-DD.', 'name' => 'Nama hari libur wajib diisi.']);
});

it('SYS-04 pengaturan satu klinik tidak mengubah klinik lain', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $adminA = makeUser(Role::Admin, $a);

    $this->actingAs($adminA)->put('/settings/clinic', payloadPengaturan())->assertSessionHasNoErrors();

    expect(ClinicModel::withoutGlobalScopes()->find($b->id)->timezone)->toBe('Asia/Jakarta');
});

it('SYS-04 formulir hari libur adalah halaman sendiri dan simpan kembali ke pengaturan klinik', function () {
    $admin = makeUser(Role::Admin, makeClinic());

    $this->actingAs($admin)->get('/settings/holidays/create')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/HolidayForm'));
    $this->actingAs($admin)->post('/settings/holidays', ['date' => '2026-12-25', 'name' => 'Hari raya uji'])->assertRedirect('/settings/clinic');
    $this->actingAs(makeUser(Role::Dokter, makeClinic('Lain')))->get('/settings/holidays/create')->assertForbidden();
});
