<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Src\IdentityAccess\Domain\Role;
use Src\Patient\Infrastructure\Eloquent\PatientConsentModel;
use Src\Patient\Infrastructure\Eloquent\PatientMedicalHistoryModel;
use Src\Patient\Infrastructure\Eloquent\PatientModel;
use Src\Shared\Infrastructure\Audit\AuditLogModel;

function daftarkan(object $test, object $user, array $o = []): string
{
    $test->actingAs($user)->post('/patients', payloadPasien($o))->assertSessionHasNoErrors();
    tenant()->set($user->clinic_id, $user->branch_id);

    return PatientModel::query()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail()->id;
}

it('PAS-01 resepsionis mendaftarkan pasien dengan nomor RM otomatis dan persetujuan tercatat', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);

    $this->actingAs($user)->post('/patients', payloadPasien())->assertSessionHasNoErrors()->assertRedirect();

    tenant()->set($klinik->id);
    $pasien = PatientModel::query()->firstOrFail();
    expect($pasien->medical_record_no)->toBe('RM-000001')
        ->and($pasien->name)->toBe('Budi Santoso')
        ->and($pasien->data_consent_at)->not->toBeNull()
        ->and(PatientConsentModel::query()->where('patient_id', $pasien->id)->where('recorded_by', $user->id)->count())->toBe(1);
});

it('PAS-01 nomor RM berurutan dan tidak bentrok antar klinik', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $userA = makeUser(Role::Resepsionis, $a);
    $userB = makeUser(Role::Resepsionis, $b);

    $this->actingAs($userA)->post('/patients', payloadPasien());
    $this->actingAs($userA)->post('/patients', payloadPasien(['name' => 'Citra Lestari', 'nik' => '9999010202910002', 'birth_date' => '1991-02-02', 'phone' => '08111111111']));
    $this->actingAs($userB)->post('/patients', payloadPasien());

    tenant()->set($a->id);
    expect(PatientModel::query()->orderBy('medical_record_no')->pluck('medical_record_no')->all())->toBe(['RM-000001', 'RM-000002']);
    tenant()->set($b->id);
    expect(PatientModel::query()->pluck('medical_record_no')->all())->toBe(['RM-000001']);
});

it('PAS-01 NIK dan nomor HP tersimpan terenkripsi, bukan teks biasa', function () {
    $klinik = makeClinic();
    daftarkan($this, makeUser(Role::Resepsionis, $klinik));

    $baris = DB::table('patients')->first();
    expect($baris->nik_encrypted)->not->toContain('9999010101900001')
        ->and($baris->phone_encrypted)->not->toContain('08123456789')->not->toContain('628123456789')
        ->and($baris->emergency_contact)->not->toContain('Ani Santoso')
        ->and($baris->nik_hash)->toHaveLength(64)->not->toContain('9999010101900001');
});

it('PAS-01 NIK wajib; tanpa NIK harus ada alasan', function () {
    $user = makeUser(Role::Resepsionis);

    $this->actingAs($user)->post('/patients', payloadPasien(['nik' => '']))->assertSessionHasErrors('domain');
    $this->actingAs($user)->post('/patients', payloadPasien(['nik' => '', 'nik_missing_reason' => 'Bayi baru lahir, NIK menyusul', 'birth_date' => now()->subMonths(2)->format('Y-m-d'), 'guardian_name' => 'Ibu Bayi', 'guardian_relation' => 'Ibu']))
        ->assertSessionHasNoErrors();
});

it('PAS-01 format NIK dan nomor HP yang salah ditolak', function () {
    $user = makeUser(Role::Resepsionis);

    $this->actingAs($user)->post('/patients', payloadPasien(['nik' => '123']))->assertSessionHasErrors('domain');
    $this->actingAs($user)->post('/patients', payloadPasien(['phone' => 'abc']))->assertSessionHasErrors('domain');
    $this->actingAs($user)->post('/patients', payloadPasien(['birth_date' => '01/01/1990']))->assertSessionHasErrors('birth_date');
});

it('PAS-05 pendaftaran tanpa persetujuan data ditolak', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);

    $this->actingAs($user)->post('/patients', payloadPasien(['consent' => false]))->assertSessionHasErrors('domain');

    tenant()->set($klinik->id);
    expect(PatientModel::query()->count())->toBe(0);
});

it('PAS-06 pasien di bawah 18 tahun wajib punya wali', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);
    $anak = ['name' => 'Dina Kecil', 'nik' => '9999010303150003', 'birth_date' => now()->subYears(10)->format('Y-m-d'), 'phone' => ''];

    $this->actingAs($user)->post('/patients', payloadPasien($anak))->assertSessionHasErrors('domain');
    $this->actingAs($user)->post('/patients', payloadPasien($anak + ['guardian_name' => 'Rina Kecil', 'guardian_relation' => 'Ibu', 'guardian_phone' => '08122222222']))
        ->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $id = PatientModel::query()->firstOrFail()->id;
    $this->actingAs($user)->get("/patients/{$id}")->assertInertia(fn ($p) => $p->where('patient.guardian_name', 'Rina Kecil')->where('patient.is_minor', true));
});

it('PAS-03 NIK yang sama menahan pendaftaran sampai dikonfirmasi', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);
    daftarkan($this, $user);

    $this->actingAs($user)->post('/patients', payloadPasien(['name' => 'Nama Lain', 'birth_date' => '1985-05-05', 'phone' => '08199999999']))
        ->assertSessionHasErrors('domain');
    tenant()->set($klinik->id);
    expect(PatientModel::query()->count())->toBe(1);

    $this->actingAs($user)->post('/patients', payloadPasien(['name' => 'Nama Lain', 'birth_date' => '1985-05-05', 'phone' => '08199999999', 'acknowledge_duplicates' => true]))
        ->assertSessionHasNoErrors();
    tenant()->set($klinik->id);
    expect(PatientModel::query()->count())->toBe(2);
});

it('PAS-03 tanggal lahir sama dengan nama mirip atau HP sama dianggap calon duplikat', function () {
    $user = makeUser(Role::Resepsionis);
    daftarkan($this, $user);

    $mirip = $this->actingAs($user)->postJson('/internal-api/patients/duplicate-check', payloadPasien(['name' => 'Budi Santosa', 'nik' => '9999010404900004', 'phone' => '08100000000']))
        ->assertOk()->json('data');
    $samaHp = $this->actingAs($user)->postJson('/internal-api/patients/duplicate-check', payloadPasien(['name' => 'Orang Berbeda', 'nik' => '9999010505900005']))
        ->assertOk()->json('data');
    $berbeda = $this->actingAs($user)->postJson('/internal-api/patients/duplicate-check', payloadPasien(['name' => 'Orang Berbeda', 'nik' => '9999010505900005', 'phone' => '08100000000', 'birth_date' => '1970-07-07']))
        ->assertOk()->json('data');

    expect($mirip)->toHaveCount(1)->and($mirip[0]['reason'])->toBe('similar')
        ->and($samaHp)->toHaveCount(1)
        ->and($berbeda)->toBe([]);
});

it('PAS-03 NIK sama terdeteksi sebagai duplikat dengan alasan nik', function () {
    $user = makeUser(Role::Resepsionis);
    daftarkan($this, $user);

    $hasil = $this->actingAs($user)->postJson('/internal-api/patients/duplicate-check', payloadPasien(['name' => 'Siapa Saja', 'birth_date' => '1960-01-01']))->assertOk()->json('data');

    expect($hasil)->toHaveCount(1)->and($hasil[0]['reason'])->toBe('nik');
});

it('PAS-03 duplikat tidak melintasi klinik', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    daftarkan($this, makeUser(Role::Resepsionis, $a));

    $this->actingAs(makeUser(Role::Resepsionis, $b))->post('/patients', payloadPasien())->assertSessionHasNoErrors();
    tenant()->set($b->id);
    expect(PatientModel::query()->count())->toBe(1);
});

it('PAS-02 mencari lewat nama, nomor RM, NIK, dan nomor HP', function () {
    $user = makeUser(Role::Resepsionis);
    daftarkan($this, $user);
    daftarkan($this, $user, ['name' => 'Citra Lestari Dewi', 'nik' => '9999010202910002', 'birth_date' => '1991-02-02', 'phone' => '08111111111']);

    $cari = fn (string $q) => array_column($this->actingAs($user)->postJson('/internal-api/patients/search', ['q' => $q])->assertOk()->json('data'), 'name');

    expect($cari('budi'))->toBe(['Budi Santoso'])
        ->and($cari('lestari'))->toBe(['Citra Lestari Dewi'])
        ->and($cari('dewi citra'))->toBe(['Citra Lestari Dewi'])
        ->and($cari('RM-000002'))->toBe(['Citra Lestari Dewi'])
        ->and($cari('9999010101900001'))->toBe(['Budi Santoso'])
        ->and($cari('08123456789'))->toBe(['Budi Santoso'])
        ->and($cari('+62 811-1111-111'))->toBe(['Citra Lestari Dewi'])
        ->and($cari('tidak ada'))->toBe([]);
});

it('PAS-02 hasil pencarian tidak memuat NIK dan nomor HP tersamar', function () {
    $user = makeUser(Role::Resepsionis);
    daftarkan($this, $user);

    $hasil = $this->actingAs($user)->postJson('/internal-api/patients/search', ['q' => 'budi'])->json('data.0');

    expect($hasil)->not->toHaveKey('nik')->and($hasil['phone'])->toBe('0812***6789');
});

it('PAS-02 pencarian dibatasi 20 hasil dan wildcard dicari apa adanya', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);
    daftarkan($this, $user);
    $baris = [];
    for ($i = 1; $i <= 25; $i++) {
        $baris[] = ['id' => sprintf('01J00000000000000000%06d', $i), 'clinic_id' => $klinik->id, 'medical_record_no' => "X-{$i}", 'name' => "Pasien Uji {$i}", 'name_search' => "pasien uji {$i}", 'birth_date' => '1980-01-01', 'sex' => 'male', 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('patients')->insert($baris);

    expect($this->actingAs($user)->postJson('/internal-api/patients/search', ['q' => 'pasien uji'])->json('meta.count'))->toBe(20);
    expect($this->actingAs($user)->postJson('/internal-api/patients/search', ['q' => '%'])->json('data'))->toBe([]);
});

it('PAS-02 pencarian tidak melintasi klinik', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    daftarkan($this, makeUser(Role::Resepsionis, $a));

    $this->actingAs(makeUser(Role::Resepsionis, $b))->postJson('/internal-api/patients/search', ['q' => 'budi'])->assertOk()->assertJsonPath('data', []);
});

it('PAS-02 pencarian wajib punya kata kunci dan izin patient.view', function () {
    $this->actingAs(makeUser(Role::Resepsionis))->postJson('/internal-api/patients/search', [])->assertStatus(422);
    $this->actingAs(makeUser(Role::Kasir))->postJson('/internal-api/patients/search', ['q' => 'a'])->assertOk();
});

it('PAS-01 mengubah data pasien tercatat di audit dengan NIK tersamar', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);
    $id = daftarkan($this, $user);

    $this->actingAs($user)->put("/patients/{$id}", payloadPasien(['address' => 'Alamat Baru 5', 'nik' => '9999010101900009']))->assertSessionHasNoErrors();

    tenant()->set($klinik->id);
    $log = AuditLogModel::query()->where('action', 'patient.updated')->firstOrFail();
    expect($log->before['address'])->toBe('Jl. Contoh No. 1')->and($log->after['address'])->toBe('Alamat Baru 5')
        ->and($log->after['nik'])->toBe('************0009');
    expect(json_encode(AuditLogModel::query()->get()->toArray()))->not->toContain('9999010101900');
});

it('PAS-01 mengubah pasien dengan NIK pasien lain ditahan sebagai duplikat', function () {
    $klinik = makeClinic();
    $user = makeUser(Role::Resepsionis, $klinik);
    daftarkan($this, $user);
    $kedua = daftarkan($this, $user, ['name' => 'Citra Lestari', 'nik' => '9999010202910002', 'birth_date' => '1991-02-02', 'phone' => '08111111111']);

    $this->actingAs($user)->put("/patients/{$kedua}", payloadPasien(['name' => 'Citra Lestari', 'birth_date' => '1991-02-02', 'phone' => '08111111111']))
        ->assertSessionHasErrors('domain');
});

it('PAS-01 pasien diarsipkan lewat soft delete oleh Admin saja', function () {
    $klinik = makeClinic();
    $id = daftarkan($this, makeUser(Role::Resepsionis, $klinik));

    $this->actingAs(makeUser(Role::Resepsionis, $klinik))->delete("/patients/{$id}")->assertForbidden();
    $this->actingAs(makeUser(Role::Admin, $klinik))->delete("/patients/{$id}")->assertRedirect('/patients');

    tenant()->set($klinik->id);
    expect(PatientModel::query()->count())->toBe(0)->and(PatientModel::withTrashed()->count())->toBe(1)
        ->and(AuditLogModel::query()->where('action', 'patient.archived')->exists())->toBeTrue();
});

it('PAS-04 riwayat medis alergi, penyakit sistemik, obat rutin dicatat dan dihapus dengan jejak', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $id = daftarkan($this, $admin);

    $this->actingAs($admin)->post("/patients/{$id}/history", ['type' => 'allergy', 'name' => 'Penisilin', 'severity' => 'severe'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post("/patients/{$id}/history", ['type' => 'systemic_disease', 'name' => 'Diabetes melitus'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post("/patients/{$id}/history", ['type' => 'regular_medication', 'name' => 'Metformin 500 mg'])->assertSessionHasNoErrors();

    $this->actingAs($admin)->get("/patients/{$id}")->assertInertia(fn ($p) => $p->has('histories', 3)->where('histories.0.is_warning', true));

    tenant()->set($klinik->id);
    $alergi = PatientMedicalHistoryModel::query()->where('type', 'allergy')->firstOrFail();
    $this->actingAs($admin)->delete("/patients/{$id}/history/{$alergi->id}")->assertRedirect();
    tenant()->set($klinik->id);
    expect(PatientMedicalHistoryModel::query()->count())->toBe(2)->and(PatientMedicalHistoryModel::withTrashed()->count())->toBe(3)
        ->and(AuditLogModel::query()->whereIn('action', ['patient.history_added', 'patient.history_removed'])->count())->toBe(4);
});

it('PAS-04 kehamilan hanya untuk pasien perempuan dan jenis riwayat harus dikenal', function () {
    $admin = makeUser(Role::Admin);
    $laki = daftarkan($this, $admin);

    $this->actingAs($admin)->post("/patients/{$laki}/history", ['type' => 'pregnancy', 'name' => 'Trimester 2'])->assertSessionHasErrors('domain');
    $this->actingAs($admin)->post("/patients/{$laki}/history", ['type' => 'lain', 'name' => 'x'])->assertSessionHasErrors('type');
});

it('PAS-04 perawat dan dokter dapat memperbarui riwayat medis saat anamnesis; kasir tidak', function () {
    $klinik = makeClinic();
    $id = daftarkan($this, makeUser(Role::Admin, $klinik));

    $this->actingAs(makeUser(Role::Perawat, $klinik))->post("/patients/{$id}/history", ['type' => 'allergy', 'name' => 'Lateks'])->assertSessionHasNoErrors();
    $this->actingAs(makeUser(Role::Dokter, $klinik))->post("/patients/{$id}/history", ['type' => 'allergy', 'name' => 'Anestesi lokal'])->assertSessionHasNoErrors();
    $this->actingAs(makeUser(Role::Kasir, $klinik))->post("/patients/{$id}/history", ['type' => 'allergy', 'name' => 'X'])->assertForbidden();
});

it('PAS-05 persetujuan baru dicatat dengan versi teks dan pencatat', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $id = daftarkan($this, $admin);

    $this->actingAs($admin)->post("/patients/{$id}/consent")->assertRedirect();

    $this->actingAs($admin)->get("/patients/{$id}")->assertInertia(fn ($p) => $p->has('consents', 2)->where('consents.0.version', '1')->where('consents.0.recorded_by', $admin->name));
});

it('PAS-01 membuka rekam pasien tercatat di access log', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $id = daftarkan($this, $admin);

    $this->actingAs($admin)->get("/patients/{$id}")->assertOk();

    expect(DB::table('access_logs')->where('patient_id', $id)->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('PAS-01 pasien klinik lain tidak dapat dibuka, diubah, atau diarsipkan', function () {
    $a = makeClinic('A');
    $b = makeClinic('B');
    $id = daftarkan($this, makeUser(Role::Admin, $a));
    $adminB = makeUser(Role::Admin, $b);

    $this->actingAs($adminB)->get("/patients/{$id}")->assertNotFound();
    $this->actingAs($adminB)->get("/patients/{$id}/edit")->assertNotFound();
    $this->actingAs($adminB)->put("/patients/{$id}", payloadPasien())->assertNotFound();
    $this->actingAs($adminB)->delete("/patients/{$id}")->assertNotFound();
    $this->actingAs($adminB)->post("/patients/{$id}/history", ['type' => 'allergy', 'name' => 'X'])->assertNotFound();
});

it('PAS-01 halaman pasien menyamarkan NIK pada tampilan dan menampilkannya penuh di formulir ubah', function () {
    $admin = makeUser(Role::Admin);
    $id = daftarkan($this, $admin);

    $this->actingAs($admin)->get("/patients/{$id}")->assertInertia(fn ($p) => $p->where('patient.nik', '************0001'));
    $this->actingAs($admin)->get("/patients/{$id}/edit")->assertInertia(fn ($p) => $p->component('Patients/Form')->where('patient.nik', '9999010101900001'));
});

it('PAS-01 izin: dokter dan perawat melihat pasien tetapi tidak mendaftarkan atau mengubah', function () {
    $klinik = makeClinic();
    $id = daftarkan($this, makeUser(Role::Resepsionis, $klinik));

    foreach ([Role::Dokter, Role::Perawat] as $peran) {
        $user = makeUser($peran, $klinik);
        $this->actingAs($user)->get('/patients')->assertOk();
        $this->actingAs($user)->get("/patients/{$id}")->assertOk();
        $this->actingAs($user)->get('/patients/create')->assertForbidden();
        $this->actingAs($user)->put("/patients/{$id}", payloadPasien())->assertForbidden();
    }
});

it('PAS-01 halaman daftar dan formulir pasien dirender', function () {
    $user = makeUser(Role::Resepsionis);

    $this->actingAs($user)->get('/patients')->assertOk()->assertInertia(fn ($p) => $p->component('Patients/Index'));
    $this->actingAs($user)->get('/patients/create')->assertOk()->assertInertia(fn ($p) => $p->component('Patients/Form')->where('patient', null)->has('consent.text'));
});

it('PAS-04 formulir riwayat medis adalah halaman sendiri dan simpan kembali ke rekam pasien', function () {
    $klinik = makeClinic();
    $admin = makeUser(Role::Admin, $klinik);
    $id = makePatient($klinik, 'Pasien Riwayat');

    $this->actingAs($admin)->get("/patients/{$id}/history/create")->assertOk()->assertInertia(fn ($p) => $p
        ->component('Patients/HistoryForm')->where('patient.name', 'Pasien Riwayat')->has('historyTypes')->has('severities'));
    $this->actingAs($admin)->post("/patients/{$id}/history", ['type' => 'allergy', 'name' => 'Lateks', 'severity' => 'mild'])->assertRedirect("/patients/{$id}");
    $this->actingAs(makeUser(Role::Kasir, $klinik))->get("/patients/{$id}/history/create")->assertForbidden();
});
