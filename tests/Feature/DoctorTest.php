<?php

declare(strict_types=1);

use Src\Clinic\Domain\FeeMode;
use Src\Clinic\Domain\FeeRule;
use Src\Clinic\Domain\FeeScheme;
use Src\Clinic\Domain\InvalidDoctorData;
use Src\Clinic\Domain\ScheduleSlot;
use Src\Clinic\Domain\WeeklySchedule;
use Src\Clinic\Infrastructure\Eloquent\DoctorModel;
use Src\Clinic\Infrastructure\Eloquent\DoctorScheduleModel;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

function payloadDokter(array $override = []): array
{
    return [
        'name' => 'drg. Sari Wulandari',
        'sip_no' => 'SIP-123/2025',
        'specialty' => 'Konservasi gigi',
        'branch_id' => '',
        'user_id' => '',
        'fee_mode' => 'percentage',
        'fee_value' => '30',
        'fee_overrides' => [['code' => 'CABUT', 'mode' => 'fixed', 'value' => '75000']],
        'schedule' => [
            ['weekday' => 1, 'start' => '09:00', 'end' => '12:00'],
            ['weekday' => 1, 'start' => '14:00', 'end' => '17:00'],
            ['weekday' => 3, 'start' => '09:00', 'end' => '12:00'],
        ],
        ...$override,
    ];
}

it('MST-02 jasa persentase dihitung dengan bilangan bulat dan dibulatkan ke bawah', function () {
    expect((new FeeRule(FeeMode::Percentage, 3000))->feeFor(250000))->toBe(75000)
        ->and((new FeeRule(FeeMode::Percentage, 3333))->feeFor(100))->toBe(33)
        ->and((new FeeRule(FeeMode::Fixed, 50000))->feeFor(999999))->toBe(50000);
});

it('MST-02 aturan jasa menolak persentase di atas 100 dan nominal negatif', function () {
    expect(fn () => new FeeRule(FeeMode::Percentage, 10001))->toThrow(InvalidDoctorData::class)
        ->and(fn () => new FeeRule(FeeMode::Fixed, -1))->toThrow(InvalidDoctorData::class);
});

it('MST-02 pengecualian per tindakan menimpa aturan bawaan', function () {
    $skema = new FeeScheme(new FeeRule(FeeMode::Percentage, 3000), ['CABUT' => new FeeRule(FeeMode::Fixed, 75000)]);

    expect($skema->ruleFor('CABUT')->mode)->toBe(FeeMode::Fixed)
        ->and($skema->ruleFor('TAMBAL')->mode)->toBe(FeeMode::Percentage)
        ->and(FeeScheme::fromArray($skema->toArray())->toArray())->toBe($skema->toArray());
});

it('MST-02 jadwal menolak jam tumpang tindih pada hari yang sama tetapi boleh berbeda hari', function () {
    $ok = new WeeklySchedule([new ScheduleSlot(1, '09:00', '12:00'), new ScheduleSlot(2, '09:00', '12:00'), new ScheduleSlot(1, '12:00', '15:00')]);
    expect($ok->covers(1, '12:00'))->toBeTrue()->and($ok->covers(1, '15:00'))->toBeFalse();

    expect(fn () => new WeeklySchedule([new ScheduleSlot(1, '09:00', '12:00'), new ScheduleSlot(1, '11:00', '13:00')]))
        ->toThrow(InvalidDoctorData::class);
    expect(fn () => new ScheduleSlot(1, '12:00', '09:00'))->toThrow(InvalidDoctorData::class);
    expect(fn () => new ScheduleSlot(8, '09:00', '10:00'))->toThrow(InvalidDoctorData::class);
});

it('MST-02 Admin menambah dokter lengkap dengan jadwal dan skema jasa, tercatat di audit', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    $this->actingAs($admin)->post('/settings/doctors', payloadDokter())->assertSessionHasNoErrors()->assertRedirect('/settings/doctors');

    tenant()->set($klinik->id);
    $dokter = DoctorModel::query()->firstOrFail();
    expect($dokter->name)->toBe('drg. Sari Wulandari')
        ->and($dokter->fee_scheme['default'])->toBe(['mode' => 'percentage', 'value' => 3000])
        ->and($dokter->fee_scheme['overrides']['CABUT'])->toBe(['mode' => 'fixed', 'value' => 75000])
        ->and(DoctorScheduleModel::query()->where('doctor_id', $dokter->id)->count())->toBe(3)
        ->and(AuditLogModel::query()->where('action', 'doctor.created')->where('subject_id', $dokter->id)->exists())->toBeTrue();
});

it('MST-02 persentase dengan desimal disimpan sebagai basis poin', function () {
    $klinik = makeClinic();
    $this->actingAs(makeUser(Role::Admin, $klinik))->post('/settings/doctors', payloadDokter(['fee_value' => '32.5', 'fee_overrides' => []]))->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    expect(DoctorModel::query()->firstOrFail()->fee_scheme['default']['value'])->toBe(3250);
});

it('MST-02 menolak jadwal bertumpuk, persentase lebih dari 100, dan nominal desimal', function () {
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->post('/settings/doctors', payloadDokter(['schedule' => [
        ['weekday' => 2, 'start' => '09:00', 'end' => '12:00'], ['weekday' => 2, 'start' => '11:00', 'end' => '13:00'],
    ]]))->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post('/settings/doctors', payloadDokter(['fee_value' => '101']))->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post('/settings/doctors', payloadDokter(['fee_mode' => 'fixed', 'fee_value' => '1000.5']))->assertSessionHasErrors('domain');

    expect(DoctorModel::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('MST-02 mengubah jadwal mengganti blok lama tanpa menghapus riwayat audit', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/settings/doctors', payloadDokter());
    tenant()->set($klinik->id);
    $id = DoctorModel::query()->firstOrFail()->id;

    $this->actingAs($admin)->put("/settings/doctors/{$id}", payloadDokter(['schedule' => [['weekday' => 5, 'start' => '08:00', 'end' => '10:00']]]))
        ->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $jadwal = DoctorScheduleModel::query()->where('doctor_id', $id)->get();
    expect($jadwal)->toHaveCount(1)->and($jadwal[0]->weekday)->toBe(5);
    $log = AuditLogModel::query()->where('action', 'doctor.updated')->firstOrFail();
    expect($log->before['schedule'])->toHaveCount(3)->and($log->after['schedule'])->toHaveCount(1);
});

it('MST-02 dokter dinonaktifkan dan diaktifkan lagi tanpa dihapus', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $this->actingAs($admin)->post('/settings/doctors', payloadDokter());
    tenant()->set($klinik->id);
    $id = DoctorModel::query()->firstOrFail()->id;

    $this->actingAs($admin)->post("/settings/doctors/{$id}/deactivate")->assertRedirect();
    tenant()->set($klinik->id);
    expect(DoctorModel::query()->findOrFail($id)->is_active)->toBeFalse();

    $this->actingAs($admin)->post("/settings/doctors/{$id}/activate")->assertRedirect();
    tenant()->set($klinik->id);
    expect(DoctorModel::query()->findOrFail($id)->is_active)->toBeTrue();
});

it('MST-02 menautkan akun hanya untuk pengguna Dokter aktif dan satu akun satu dokter', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $akunDokter = makeUser(Role::Dokter, $klinik);
    $akunKasir = makeUser(Role::Kasir, $klinik);

    $this->actingAs($admin)->post('/settings/doctors', payloadDokter(['user_id' => $akunKasir->id]))->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post('/settings/doctors', payloadDokter(['user_id' => $akunDokter->id]))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/settings/doctors', payloadDokter(['name' => 'drg. Lain', 'user_id' => $akunDokter->id]))->assertSessionHasErrors('domain');

    tenant()->set($klinik->id);
    expect(DoctorModel::query()->count())->toBe(1);
});

it('MST-02 akun dokter klinik lain tidak dapat ditautkan', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $akunB = makeUser(Role::Dokter, $b);

    $this->actingAs(makeUser(Role::Admin, $a))->post('/settings/doctors', payloadDokter(['user_id' => $akunB->id]))->assertSessionHasErrors('domain');
});

it('MST-02 halaman dokter klinik lain tidak dapat dibuka dan peran lain ditolak', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $adminA = makeUser(Role::Admin, $a);
    $this->actingAs($adminA)->post('/settings/doctors', payloadDokter());
    tenant()->set($a->id);
    $id = DoctorModel::query()->firstOrFail()->id;

    $this->actingAs(makeUser(Role::Admin, $b))->get("/settings/doctors/{$id}/edit")->assertNotFound();
    $this->actingAs(makeUser(Role::Resepsionis, $a))->get('/settings/doctors')->assertForbidden();
});

it('MST-02 daftar dan form dokter dirender', function () {
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->get('/settings/doctors')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Doctors/Index')->has('doctors'));
    $this->actingAs($admin)->get('/settings/doctors/create')->assertOk()->assertInertia(fn ($p) => $p->component('Settings/Doctors/Form')->where('doctor', null)->has('feeModes', 2));
});
