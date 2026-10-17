<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Clinic\Infrastructure\Eloquent\ToothConditionModel;
use Src\Clinical\Infrastructure\Eloquent\EncounterModel;
use Src\Clinical\Infrastructure\Eloquent\OdontogramSnapshotModel;
use Src\Clinical\Infrastructure\Eloquent\OdontogramToothStateModel;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

function ubahGigi(array $s, int $fdi, string $permukaan, string $kondisi, ?string $kunjungan = null)
{
    return test()->actingAs($s['dokter_user'])->putJson('/internal-api/encounters/'.($kunjungan ?? $s['kunjungan'])."/odontogram/teeth/{$fdi}", ['surface' => $permukaan, 'condition_code' => $kondisi]);
}

it('ODO-03 klinik baru langsung memiliki kondisi gigi bawaan', function () {
    $klinik = makeClinic();
    tenant()->set($klinik->id);

    $kode = ToothConditionModel::query()->orderBy('code')->pluck('code')->all();

    expect($kode)->toBe(['GTP', 'HLG', 'IMN', 'IMP', 'KAR', 'LNY', 'MHK', 'PSA', 'SEH', 'SIS', 'TAM']);
});

it('ODO-03 Admin menambah kondisi baru dan mengubah warna, tetapi kode tidak berubah', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);

    test()->actingAs($admin)->post('/settings/master/tooth-conditions', ['code' => 'FRK', 'name' => 'Fraktur', 'color' => '#111827', 'scope' => 'both'])->assertSessionHasNoErrors();
    tenant()->set($klinik->id);
    $id = ToothConditionModel::query()->where('code', 'FRK')->firstOrFail()->id;

    test()->actingAs($admin)->put("/settings/master/tooth-conditions/{$id}", ['code' => 'XXX', 'name' => 'Fraktur gigi', 'color' => '#222222', 'scope' => 'both'])->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $k = ToothConditionModel::query()->findOrFail($id);
    expect($k->code)->toBe('FRK')->and($k->name)->toBe('Fraktur gigi')->and($k->color)->toBe('#222222');
});

it('ODO-03 kode dan warna kondisi divalidasi', function () {
    $admin = makeUser(Role::Admin);
    $kirim = fn (array $d) => test()->actingAs($admin)->post('/settings/master/tooth-conditions', $d + ['name' => 'Uji', 'scope' => 'both']);

    $kirim(['code' => 'kar', 'color' => '#111111'])->assertSessionHasErrors('code');
    $kirim(['code' => 'KAR', 'color' => '#111111'])->assertSessionHasErrors('code');
    $kirim(['code' => 'UJI', 'color' => 'merah'])->assertSessionHasErrors('color');
    $kirim(['code' => 'UJI', 'color' => '#GGGGGG'])->assertSessionHasErrors('color');
    $kirim(['code' => 'UJI', 'color' => '#abcdef'])->assertSessionHasNoErrors();
});

it('ODO-03 hanya Admin dengan izin pengaturan yang mengelola kondisi gigi', function () {
    $klinik = makeClinic();

    test()->actingAs(makeUser(Role::Dokter, $klinik))->get('/settings/master/tooth-conditions')->assertForbidden();
    test()->actingAs(makeUser(Role::Admin, $klinik))->get('/settings/master/tooth-conditions')->assertOk();
});

it('ODO-02 dokter mencatat kondisi per permukaan dan audit mencatat sebelum dan sesudah', function () {
    $s = siapKunjungan();

    ubahGigi($s, 16, 'O', 'KAR')->assertOk()->assertJsonPath('data.condition_code', 'KAR');
    ubahGigi($s, 16, 'O', 'TAM')->assertOk();

    tenant()->set($s['klinik']->id);
    expect(OdontogramToothStateModel::query()->where('tooth_fdi', 16)->where('surface', 'O')->count())->toBe(1);
    $logs = AuditLogModel::query()->where('action', 'odontogram.condition_set')->orderBy('created_at')->orderBy('id')->get();
    expect($logs)->toHaveCount(2)->and($logs[1]->before['condition'])->toBe('KAR')->and($logs[1]->after['condition'])->toBe('TAM');
});

it('ODO-02 mencatat kondisi yang sama dua kali tidak menambah audit', function () {
    $s = siapKunjungan();
    ubahGigi($s, 16, 'O', 'KAR');
    ubahGigi($s, 16, 'O', 'KAR');

    tenant()->set($s['klinik']->id);
    expect(AuditLogModel::query()->where('action', 'odontogram.condition_set')->count())->toBe(1);
});

it('ODO-03 kondisi menentukan permukaan: seluruh gigi atau per permukaan', function () {
    $s = siapKunjungan();

    ubahGigi($s, 16, 'O', 'HLG')->assertStatus(422)->assertJsonPath('code', 'odontogram.condition_whole_tooth');
    ubahGigi($s, 16, 'W', 'KAR')->assertStatus(422)->assertJsonPath('code', 'odontogram.condition_surface_only');
    ubahGigi($s, 16, 'W', 'HLG')->assertOk();
    ubahGigi($s, 17, 'O', 'SEH')->assertOk();
    ubahGigi($s, 17, 'W', 'SEH')->assertOk();
});

it('ODO-01 gigi dengan kondisi seluruh gigi tanpa permukaan tetap dikirim sebagai objek JSON', function () {
    $s = siapKunjungan();
    ubahGigi($s, 16, 'W', 'HLG')->assertOk();

    $isi = test()->actingAs($s['dokter_user'])->get("/internal-api/patients/{$s['pasien']}/odontogram")->assertOk()->getContent();

    expect($isi)->toContain('"surfaces":{}')->and($isi)->not->toContain('"surfaces":[]')->and($isi)->toContain('"meta":{}');
});

it('ODO-01 nomor gigi, permukaan, dan kondisi yang tidak sah ditolak', function () {
    $s = siapKunjungan();

    ubahGigi($s, 19, 'O', 'KAR')->assertStatus(422)->assertJsonPath('code', 'clinical.invalid_tooth');
    ubahGigi($s, 16, 'X', 'KAR')->assertStatus(422)->assertJsonPath('code', 'odontogram.invalid_surface');
    ubahGigi($s, 16, 'O', 'ZZZ')->assertStatus(422)->assertJsonPath('code', 'odontogram.invalid_condition');
    tenant()->set($s['klinik']->id);
    ToothConditionModel::query()->where('code', 'KAR')->update(['is_active' => false]);
    ubahGigi($s, 16, 'O', 'KAR')->assertStatus(422)->assertJsonPath('code', 'odontogram.invalid_condition');
});

it('ODO-01 gigi sulung dan dewasa sama-sama dapat dicatat; dentisi snapshot mengikuti', function () {
    $s = siapKunjungan();

    ubahGigi($s, 54, 'O', 'KAR')->assertOk();
    tenant()->set($s['klinik']->id);
    expect(OdontogramSnapshotModel::query()->firstOrFail()->dentition)->toBe('primary');
    ubahGigi($s, 16, 'O', 'KAR')->assertOk();
    tenant()->set($s['klinik']->id);
    expect(OdontogramSnapshotModel::query()->firstOrFail()->dentition)->toBe('mixed');
});

it('ODO-04 satu snapshot per kunjungan dan odontogram terkini dihitung lintas kunjungan', function () {
    $s = siapKunjungan();
    ubahGigi($s, 16, 'O', 'KAR');
    ubahGigi($s, 16, 'M', 'KAR');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    majuKe('2025-06-23 09:00:00');
    $janji2 = bukaJanji($s, ['date' => '2025-06-23', 'time' => '10:00']);
    majuKe('2025-06-23 10:00:00');
    test()->actingAs($s['user'])->post("/appointments/{$janji2}/check-in")->assertSessionHasNoErrors();
    tenant()->set($s['klinik']->id);
    $kunjungan2 = EncounterModel::query()->where('appointment_id', $janji2)->firstOrFail()->id;
    ubahGigi($s, 16, 'O', 'TAM', $kunjungan2)->assertOk();
    ubahGigi($s, 26, 'W', 'IMP', $kunjungan2)->assertOk();

    tenant()->set($s['klinik']->id);
    expect(OdontogramSnapshotModel::query()->count())->toBe(2);

    $data = test()->actingAs($s['dokter_user'])->getJson("/internal-api/patients/{$s['pasien']}/odontogram?encounter={$kunjungan2}")->assertOk()->json('data');
    expect($data['teeth'][16]['surfaces']['O']['condition_code'])->toBe('TAM')
        ->and($data['teeth'][16]['surfaces']['O']['in_this_encounter'])->toBeTrue()
        ->and($data['teeth'][16]['surfaces']['M']['condition_code'])->toBe('KAR')
        ->and($data['teeth'][16]['surfaces']['M']['in_this_encounter'])->toBeFalse()
        ->and($data['teeth'][26]['whole']['condition_code'])->toBe('IMP')
        ->and(collect($data['conditions'])->pluck('code')->all())->toContain('SEH', 'KAR');
});

it('ODO-04 kunjungan yang ditutup tidak menerima perubahan odontogram', function () {
    $s = siapKunjungan();
    ubahGigi($s, 16, 'O', 'KAR');
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/diagnoses", ['diagnosis_id' => icd()]);
    test()->actingAs($s['dokter_user'])->post("/encounters/{$s['kunjungan']}/close")->assertSessionHasNoErrors();

    ubahGigi($s, 16, 'O', 'TAM')->assertStatus(409)->assertJsonPath('code', 'encounter.locked');
});

it('ODO izin: perawat boleh melihat tetapi tidak mengubah odontogram', function () {
    $s = siapKunjungan();
    ubahGigi($s, 16, 'O', 'KAR');

    test()->actingAs($s['perawat'])->getJson("/internal-api/patients/{$s['pasien']}/odontogram")->assertOk();
    test()->actingAs($s['perawat'])->putJson("/internal-api/encounters/{$s['kunjungan']}/odontogram/teeth/16", ['surface' => 'O', 'condition_code' => 'TAM'])->assertForbidden();
    test()->actingAs($s['user'])->getJson("/internal-api/patients/{$s['pasien']}/odontogram")->assertForbidden();
});

it('ODO odontogram klinik lain tidak dapat dilihat atau diubah', function () {
    $a = siapKunjungan();
    $b = siapKunjungan();
    ubahGigi($a, 16, 'O', 'KAR');

    test()->actingAs($b['dokter_user'])->getJson("/internal-api/patients/{$a['pasien']}/odontogram")->assertNotFound();
    test()->actingAs($b['dokter_user'])->putJson("/internal-api/encounters/{$a['kunjungan']}/odontogram/teeth/16", ['surface' => 'O', 'condition_code' => 'TAM'])->assertNotFound();
});

it('ODO membuka odontogram pasien tercatat di access log', function () {
    $s = siapKunjungan();

    test()->actingAs($s['dokter_user'])->getJson("/internal-api/patients/{$s['pasien']}/odontogram")->assertOk();

    expect(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('actor_id', $s['dokter_user']->id)->exists())->toBeTrue();
});

it('ODO-06 odontogram terkini dicetak ke PDF dan pencetakannya tercatat', function () {
    $s = siapKunjungan();
    tenant()->set($s['klinik']->id);
    test()->actingAs($s['dokter_user'])->putJson("/internal-api/encounters/{$s['kunjungan']}/odontogram/teeth/46", ['surface' => 'O', 'condition_code' => 'KAR'])->assertOk();
    test()->actingAs($s['dokter_user'])->putJson("/internal-api/encounters/{$s['kunjungan']}/odontogram/teeth/36", ['surface' => 'W', 'condition_code' => 'MHK'])->assertOk();
    test()->actingAs($s['dokter_user'])->putJson("/internal-api/encounters/{$s['kunjungan']}/odontogram/teeth/54", ['surface' => 'W', 'condition_code' => 'HLG'])->assertOk();

    $respons = test()->actingAs($s['dokter_user'])->get("/patients/{$s['pasien']}/odontogram/print");

    $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(str_starts_with($respons->getContent(), '%PDF'))->toBeTrue()
        ->and((string) $respons->headers->get('Content-Disposition'))->toContain('inline')
        ->and(DB::table('access_logs')->where('patient_id', $s['pasien'])->where('route', 'odontogram.print')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('subject_id', $s['pasien'])->where('action', 'odontogram.printed')->exists())->toBeTrue();
});

it('ODO-06 cetak odontogram menuntut izin odontogram.view dan hanya untuk pasien klinik sendiri', function () {
    $s = siapKunjungan();
    $kasir = makeUser(Role::Kasir, $s['klinik']);
    $lain = makeClinic('Klinik Lain');
    $dokterLain = makeUser(Role::Dokter, $lain);
    tenant()->set($s['klinik']->id);

    test()->actingAs($kasir)->get("/patients/{$s['pasien']}/odontogram/print")->assertForbidden();
    test()->actingAs($s['user'])->get("/patients/{$s['pasien']}/odontogram/print")->assertForbidden();
    test()->actingAs($s['perawat'])->get("/patients/{$s['pasien']}/odontogram/print")->assertOk();
    tenant()->set($lain->id);
    test()->actingAs($dokterLain)->get("/patients/{$s['pasien']}/odontogram/print")->assertNotFound();
});
