<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\IdentityAccess\Domain\Role;
use Src\Shared\Application\PiiProtector;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-16 07:00:00', 'Asia/Jakarta'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Pasien ganda (punya janji, kunjungan, tagihan, resep, berkas, odontogram, riwayat medis, persetujuan) dan pasien utama kosong.
 *
 * @return array<string, mixed>
 */
function siapGabung(): array
{
    $s = siapKunjungan();
    $admin = makeUser(Role::Admin, $s['klinik']);
    $utama = makePatient($s['klinik'], 'Budi Santoso Utama');
    $now = now();
    $ulid = fn () => strtoupper((string) Str::ulid());
    $ganda = $s['pasien'];

    DB::table('invoices')->insert(['id' => $inv = $ulid(), 'clinic_id' => $s['klinik']->id, 'patient_id' => $ganda, 'encounter_id' => $s['kunjungan'], 'lifecycle' => 'draft', 'status' => 'draft', 'subtotal_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('prescriptions')->insert(['id' => $ulid(), 'clinic_id' => $s['klinik']->id, 'encounter_id' => $s['kunjungan'], 'patient_id' => $ganda, 'doctor_id' => $s['dokter']->id, 'issued_at' => $now, 'status' => 'issued', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('clinical_files')->insert(['id' => $ulid(), 'clinic_id' => $s['klinik']->id, 'patient_id' => $ganda, 'encounter_id' => $s['kunjungan'], 'disk' => 'private', 'path' => 'x/y.jpg', 'mime' => 'image/jpeg', 'size' => 10, 'kind' => 'photo', 'uploaded_by' => $s['dokter_user']->id, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('odontogram_snapshots')->insert(['id' => $ulid(), 'clinic_id' => $s['klinik']->id, 'patient_id' => $ganda, 'encounter_id' => $s['kunjungan'], 'dentition' => 'permanent', 'taken_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    foreach ([['allergy', 'Penisilin'], ['systemic_disease', 'Diabetes']] as [$jenis, $nama]) {
        DB::table('patient_medical_histories')->insert(['id' => $ulid(), 'clinic_id' => $s['klinik']->id, 'patient_id' => $ganda, 'type' => $jenis, 'name' => $nama, 'created_at' => $now, 'updated_at' => $now]);
    }
    DB::table('patient_medical_histories')->insert(['id' => $ulid(), 'clinic_id' => $s['klinik']->id, 'patient_id' => $utama, 'type' => 'allergy', 'name' => 'penisilin', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('patient_consents')->insert(['id' => $ulid(), 'clinic_id' => $s['klinik']->id, 'patient_id' => $ganda, 'text_version' => 'v1', 'given_at' => $now, 'created_at' => $now]);

    return $s + ['admin' => $admin, 'utama' => $utama, 'ganda' => $ganda, 'invoice' => $inv];
}

function isiNik(string $pasien, string $nik): void
{
    $pii = app(PiiProtector::class);
    DB::table('patients')->where('id', $pasien)->update(['nik_encrypted' => $pii->encrypt($nik), 'nik_hash' => $pii->blindIndex('nik', $nik)]);
}

function gabung(array $s, array $o = [], ?object $user = null)
{
    tenant()->set($s['klinik']->id);

    return test()->actingAs($user ?? $s['admin'])->post("/patients/{$s['utama']}/merge", [
        'source_id' => $s['ganda'], 'reason' => 'Pasien terdaftar dua kali', 'confirm' => true, ...$o,
    ]);
}

it('PAS-07 Admin menggabungkan pasien ganda: semua data pindah ke pasien utama dan pasien ganda diarsipkan', function () {
    $s = siapGabung();

    gabung($s)->assertSessionHasNoErrors()->assertRedirect("/patients/{$s['utama']}");

    foreach (['appointments', 'encounters', 'invoices', 'prescriptions', 'clinical_files', 'odontogram_snapshots'] as $tabel) {
        expect(DB::table($tabel)->where('patient_id', $s['ganda'])->count())->toBe(0, "{$tabel} masih menunjuk pasien ganda")
            ->and(DB::table($tabel)->where('patient_id', $s['utama'])->count())->toBe(1, "{$tabel} belum pindah");
    }
    // Riwayat medis: Penisilin sudah ada pada pasien utama (tanpa memandang huruf besar), hanya Diabetes yang pindah.
    $riwayat = DB::table('patient_medical_histories')->where('patient_id', $s['utama'])->orderBy('name')->pluck('name')->all();
    expect($riwayat)->toBe(['Diabetes', 'penisilin']);
    expect(DB::table('patient_consents')->where('patient_id', $s['utama'])->count())->toBe(1);

    $ganda = DB::table('patients')->where('id', $s['ganda'])->first();
    expect($ganda->deleted_at)->not->toBeNull()->and($ganda->merged_into_id)->toBe($s['utama'])->and($ganda->merged_at)->not->toBeNull();
    // Tidak ada yang dihapus permanen: baris pasien ganda tetap ada (M5).
    expect(DB::table('patients')->where('id', $s['ganda'])->exists())->toBeTrue();
});

it('PAS-07 penggabungan tercatat di audit log pada kedua pasien tanpa data pribadi', function () {
    $s = siapGabung();
    gabung($s)->assertSessionHasNoErrors();

    $utama = DB::table('audit_logs')->where('subject_id', $s['utama'])->where('action', 'patient.merged')->first();
    $ganda = DB::table('audit_logs')->where('subject_id', $s['ganda'])->where('action', 'patient.merged_into')->first();
    expect($utama)->not->toBeNull()->and($ganda)->not->toBeNull()
        ->and($utama->actor_id)->toBe($s['admin']->id);
    $after = json_decode((string) $utama->after, true);
    expect($after['reason'])->toBe('Pasien terdaftar dua kali')->and($after['source_id'])->toBe($s['ganda'])
        ->and($after['moved']['Kunjungan'])->toBe(1)->and($after['moved']['Tagihan'])->toBe(1)
        ->and($after['histories'])->toBe(1)->and($after['consents'])->toBe(1);
    expect(json_decode((string) $ganda->after, true)['target_id'])->toBe($s['utama']);
});

it('PAS-07 data kosong pasien utama dilengkapi dari pasien ganda, data yang sudah terisi tidak ditimpa', function () {
    $s = siapGabung();
    DB::table('patients')->where('id', $s['ganda'])->update(['address' => 'Jl. Lama No. 1', 'occupation' => 'Guru']);
    DB::table('patients')->where('id', $s['utama'])->update(['occupation' => 'Dokter']);

    gabung($s)->assertSessionHasNoErrors();

    $utama = DB::table('patients')->where('id', $s['utama'])->first();
    expect($utama->address)->toBe('Jl. Lama No. 1')->and($utama->occupation)->toBe('Dokter');
});

it('PAS-07 NIK yang berbeda menghalangi penggabungan karena kemungkinan orang berbeda', function () {
    $s = siapGabung();
    isiNik($s['ganda'], '9999010101900001');
    isiNik($s['utama'], '9999010101900002');

    gabung($s)->assertSessionHas('error');

    expect(DB::table('patients')->where('id', $s['ganda'])->value('deleted_at'))->toBeNull()
        ->and(DB::table('encounters')->where('patient_id', $s['ganda'])->count())->toBe(1);
});

it('PAS-07 NIK pasien ganda dibawa ke pasien utama yang belum punya NIK', function () {
    $s = siapGabung();
    isiNik($s['ganda'], '9999010101900001');
    DB::table('patients')->where('id', $s['utama'])->update(['nik_missing_reason' => 'Bayi']);

    gabung($s)->assertSessionHasNoErrors();

    $utama = DB::table('patients')->where('id', $s['utama'])->first();
    expect($utama->nik_hash)->toBe(app(PiiProtector::class)->blindIndex('nik', '9999010101900001'))
        ->and(app(PiiProtector::class)->decrypt((string) $utama->nik_encrypted))->toBe('9999010101900001')
        ->and($utama->nik_missing_reason)->toBeNull();
});

it('PAS-07 alasan wajib, konfirmasi wajib, dan pasien tidak boleh digabung dengan dirinya sendiri', function () {
    $s = siapGabung();

    gabung($s, ['reason' => ''])->assertSessionHasErrors('reason');
    gabung($s, ['reason' => 'dup'])->assertSessionHasErrors('reason');
    gabung($s, ['confirm' => false])->assertSessionHasErrors('confirm');
    gabung($s, ['source_id' => $s['utama']])->assertSessionHasErrors('source_id');

    expect(DB::table('patients')->where('id', $s['ganda'])->value('merged_into_id'))->toBeNull();
});

it('PAS-07 hanya pemegang izin patient.merge (Admin) yang dapat menggabungkan', function () {
    $s = siapGabung();
    foreach ([Role::Pemilik, Role::Resepsionis, Role::Dokter, Role::Perawat, Role::Kasir] as $peran) {
        $user = makeUser($peran, $s['klinik']);
        gabung($s, [], $user)->assertForbidden();
        test()->actingAs($user)->get("/patients/{$s['utama']}/merge")->assertForbidden();
    }
    expect(DB::table('patients')->where('id', $s['ganda'])->value('deleted_at'))->toBeNull();
});

it('PAS-07 pasien klinik lain tidak dapat digabung', function () {
    $s = siapGabung();
    $lain = makeClinic('Klinik Lain');
    $asing = makePatient($lain, 'Pasien Asing');

    gabung($s, ['source_id' => $asing])->assertNotFound();
    tenant()->set($lain->id);
    expect(DB::table('patients')->where('id', $asing)->value('deleted_at'))->toBeNull();
});

it('PAS-07 pasien yang sudah digabung tidak dapat digabung lagi', function () {
    $s = siapGabung();
    gabung($s)->assertSessionHasNoErrors();

    gabung($s)->assertNotFound();
});

it('PAS-07 admin terikat satu cabang tetap memindahkan data di semua cabang', function () {
    $s = siapGabung();
    $cabang = makeBranch($s['klinik'], 'Cabang Dua');
    DB::table('appointments')->where('patient_id', $s['ganda'])->update(['branch_id' => $cabang->id]);
    $adminCabang = makeUser(Role::Admin, $s['klinik'], makeBranch($s['klinik'], 'Cabang Tiga'));

    gabung($s, [], $adminCabang)->assertSessionHasNoErrors();

    expect(DB::table('appointments')->where('patient_id', $s['ganda'])->count())->toBe(0);
});

it('PAS-07 halaman pratinjau menampilkan perbandingan dan jumlah data yang akan pindah', function () {
    $s = siapGabung();
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['admin'])->get("/patients/{$s['utama']}/merge")
        ->assertOk()->assertInertia(fn ($p) => $p->component('Patients/Merge')->where('source', null)->where('target.id', $s['utama']));

    test()->actingAs($s['admin'])->get("/patients/{$s['utama']}/merge?source={$s['ganda']}")
        ->assertOk()->assertInertia(function ($p) use ($s) {
            $p->component('Patients/Merge')->where('source.id', $s['ganda'])->where('blocker', null);
            $counts = collect($p->toArray()['props']['counts'])->pluck('count', 'label')->all();
            expect($counts['Kunjungan'])->toBe(1)->and($counts['Janji temu'])->toBe(1)->and($counts['Riwayat medis'])->toBe(2);

            return $p;
        });
});

it('PAS-07 tombol gabung hanya muncul bagi yang berhak dan NIK tidak tampil utuh di pratinjau', function () {
    $s = siapGabung();
    tenant()->set($s['klinik']->id);

    test()->actingAs($s['admin'])->get("/patients/{$s['utama']}")->assertInertia(fn ($p) => $p->where('can.merge', true));
    test()->actingAs($s['user'])->get("/patients/{$s['utama']}")->assertInertia(fn ($p) => $p->where('can.merge', false));
});
